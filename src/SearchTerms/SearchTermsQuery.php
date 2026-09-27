<?php

declare(strict_types=1);

/**
 * This file is part of the PimBay Search Query library.
 *
 * @author Jan Sarmir <sarmir@pimbay.dev>
 * @link   https://pimbay.dev
 *
 * For the full license information, see the LICENSE file.
 */

namespace PimBay\SearchQuery\Pimcore\SearchTerms;

use PimBay\SearchQuery\Pimcore\Exception\DuplicateConditionException;
use PimBay\SearchQuery\Pimcore\Exception\InvalidConditionTagException;
use PimBay\SearchQuery\Pimcore\SqlHelper;
use PimBay\SearchQuery\SearchTerms\ParsedSearchTerms;
use PimBay\SearchQuery\SearchTerms\SearchTermsConfig;
use PimBay\SearchQuery\SearchTerms\SearchTermsParser;
use Pimcore\Model\Listing\AbstractListing;

/**
 * Applies a ParsedSearchTerms as `addConditionParam()` calls against one column. `equals`/`likes`
 * are OR-grouped; `notEquals`/`notLikes` are AND-grouped.
 *
 * Each group is wrapped in explicit parentheses — required, not stylistic; see docs/DECISIONS.md.
 *
 * Positional `?` placeholders and no `$paramPrefix`, unlike search-query-doctrine — `$tag` takes
 * over the disambiguating job that prefix does there. See docs/DECISIONS.md.
 */
final readonly class SearchTermsQuery
{
    /**
     * `$tag` is interpolated into raw SQL as a comment, so it is whitelisted rather than escaped.
     * The charset excludes `*` and `/`, which is what makes closing the comment early impossible.
     */
    private const string TAG_PATTERN = '/^[\w.-]+$/';

    public function __construct(
        private SearchTermsParser $parser = new SearchTermsParser(),
    ) {
    }

    /**
     * @param string|null $tag disambiguates two otherwise-identical condition groups on the same
     *                         column; required only when applying more than one search to a column
     *
     * @throws InvalidConditionTagException if $tag contains anything but letters, digits, `_`, `.` or `-`
     * @throws DuplicateConditionException if the listing already holds an identical condition group
     */
    public function apply(
        AbstractListing $listing,
        string $column,
        ParsedSearchTerms $parsed,
        SearchTermsConfig $config,
        ?string $tag = null,
    ): void {
        if (null !== $tag && 1 !== preg_match(self::TAG_PATTERN, $tag)) {
            throw new InvalidConditionTagException($tag);
        }

        $escapeClause = SqlHelper::likeEscapeClause();
        $conditions = [];
        $params = [];

        foreach ($parsed->equals as $value) {
            $conditions[] = \sprintf('%s = ?', $column);
            $params[] = $value;
        }
        foreach ($parsed->likes as $value) {
            $conditions[] = \sprintf('%s LIKE ? %s', $column, $escapeClause);
            $params[] = $this->toLikePattern($value, $config);
        }

        $this->addGroup($listing, $conditions, ' OR ', $params, $column, $tag);

        $conditions = [];
        $params = [];

        foreach ($parsed->notEquals as $value) {
            $conditions[] = $this->negation(\sprintf('%s != ?', $column), $column, $config);
            $params[] = $value;
        }
        foreach ($parsed->notLikes as $value) {
            $conditions[] = $this->negation(
                \sprintf('%s NOT LIKE ? %s', $column, $escapeClause),
                $column,
                $config,
            );
            $params[] = $this->toLikePattern($value, $config);
        }

        $this->addGroup($listing, $conditions, ' AND ', $params, $column, $tag);
    }

    /**
     * @throws InvalidConditionTagException if $tag contains anything but letters, digits, `_`, `.` or `-`
     * @throws DuplicateConditionException if the listing already holds an identical condition group
     */
    public function applyString(
        AbstractListing $listing,
        string $column,
        string $text,
        SearchTermsConfig $config,
        ?string $tag = null,
    ): void {
        $this->apply($listing, $column, $this->parser->parseString($text, $config), $config, $tag);
    }

    /**
     * @param string[] $conditions
     * @param string[] $params
     */
    private function addGroup(
        AbstractListing $listing,
        array $conditions,
        string $glue,
        array $params,
        string $column,
        ?string $tag,
    ): void {
        if ([] === $conditions) {
            return;
        }

        $fragment = '('.implode($glue, $conditions).')';

        if (null !== $tag) {
            $fragment .= ' /* '.$tag.' */';
        }

        // Pimcore keys conditionParams by the fragment string, so an identical one silently replaces the
        // earlier condition. Probing a clone detects it without mutating the caller's listing.
        $probe = clone $listing;
        $probe->addConditionParam($fragment, $params);

        if (\count($probe->getConditionParams()) === \count($listing->getConditionParams())) {
            throw new DuplicateConditionException($column, $tag);
        }

        $listing->addConditionParam($fragment, $params);
    }

    /**
     * `value != 'red'` is NULL, not TRUE, for a row with no value, and WHERE keeps only TRUE — so a bare
     * negation drops every such row, which is not what `-red` means.
     */
    private function negation(string $predicate, string $column, SearchTermsConfig $config): string
    {
        return $config->ignoredTermsMatchNull ? \sprintf('(%s OR %s IS NULL)', $predicate, $column) : $predicate;
    }

    private function toLikePattern(string $value, SearchTermsConfig $config): string
    {
        // Escaping up front would neutralise any marker SqlHelper also escapes — `%`, `_` or the
        // escape character itself — turning the caller's wildcard into a literal that never matches.
        $escaped = implode('%', array_map(SqlHelper::escapeLike(...), $this->splitOnMarkers($value, $config)));

        return $config->anywhere ? '%'.$escaped : $escaped;
    }

    /**
     * Neither `preg_split()` nor a hand-advanced cursor, for reasons that outlive this method — read
     * docs/DECISIONS.md before changing the loop or the `0 !==` comparison below.
     *
     * @return list<string>
     */
    private function splitOnMarkers(string $value, SearchTermsConfig $config): array
    {
        $chunks = [];
        $chunk = '';
        $skip = 0;

        foreach (str_split($value) as $offset => $character) {
            // `> 0` would be equivalent — the counter is never negative — but it makes a wrong starting
            // value unobservable, and so unkillable by any test.
            if (0 !== $skip) {
                --$skip;

                continue;
            }

            $marker = $this->markerAt($value, $offset, $config->likeMarkers);

            if (null === $marker) {
                $chunk .= $character;

                continue;
            }

            $chunks[] = $chunk;
            $chunk = '';
            $skip = \strlen($marker) - 1;
        }

        $chunks[] = $chunk;

        return $chunks;
    }

    /**
     * SearchTermsConfig hands the markers over longest-first, so the first hit here is the longest one and
     * a marker that prefixes another never shadows it.
     *
     * @param list<string> $markers
     */
    private function markerAt(string $value, int $offset, array $markers): ?string
    {
        foreach ($markers as $marker) {
            if (substr($value, $offset, \strlen($marker)) === $marker) {
                return $marker;
            }
        }

        return null;
    }
}
