<?php

declare(strict_types=1);

namespace PimBay\SearchQuery\Pimcore\Tests\Unit\SearchTerms;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PimBay\SearchQuery\Pimcore\Exception\DuplicateConditionException;
use PimBay\SearchQuery\Pimcore\Exception\InvalidConditionTagException;
use PimBay\SearchQuery\Pimcore\SearchTerms\SearchTermsQuery;
use PimBay\SearchQuery\Pimcore\Tests\Fixture\SpyListing;
use PimBay\SearchQuery\SearchTerms\ParsedSearchTerms;
use PimBay\SearchQuery\SearchTerms\SearchTermsConfig;

final class SearchTermsQueryTest extends TestCase
{
    /**
     * @return iterable<string, array{string}>
     */
    public static function invalidTagProvider(): iterable
    {
        yield 'empty' => [''];
        yield 'closes the comment early' => ['a */ OR 1=1 /*'];
        yield 'whitespace' => ['a b'];
        yield 'slash' => ['a/b'];
        yield 'star' => ['a*b'];
        yield 'quote' => ["a'b"];
    }

    #[Test]
    public function equalsAndLikesAreOrGroupedInOneCall(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: ['foo'], notEquals: [], likes: ['bar'], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig());

        self::assertSame([["(name = ? OR name LIKE ? ESCAPE '~')", ['foo', '%bar']]], self::conditionsOf($listing));
    }

    #[Test]
    public function notEqualsAndNotLikesAreAndGroupedInOneCall(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: ['foo'], likes: [], notLikes: ['bar']);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig());

        self::assertSame(
            [["((name != ? OR name IS NULL) AND (name NOT LIKE ? ESCAPE '~' OR name IS NULL))", ['foo', '%bar']]],
            self::conditionsOf($listing),
        );
    }

    #[Test]
    public function ignoredTermsMatchNullFalseRestoresTheBareStricterPredicates(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: ['foo'], likes: [], notLikes: ['bar']);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig(ignoredTermsMatchNull: false));

        self::assertSame(
            [["(name != ? AND name NOT LIKE ? ESCAPE '~')", ['foo', '%bar']]],
            self::conditionsOf($listing),
        );
    }

    #[Test]
    public function positiveGroupsAreUntouchedByNegationsMatchNull(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: ['foo'], notEquals: [], likes: ['bar'], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig());

        self::assertSame([["(name = ? OR name LIKE ? ESCAPE '~')", ['foo', '%bar']]], self::conditionsOf($listing));
    }

    #[Test]
    public function positiveAndNegativeGroupsProduceTwoSeparateCalls(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: ['foo'], notEquals: ['baz'], likes: [], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig());

        self::assertSame(
            [['(name = ?)', ['foo']], ['((name != ? OR name IS NULL))', ['baz']]],
            self::conditionsOf($listing),
        );
    }

    #[Test]
    public function emptyParsedTermsCallAddConditionParamNotAtAll(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: [], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig());

        self::assertSame([], self::conditionsOf($listing));
        self::assertSame([], $listing->log->calls);
    }

    #[Test]
    public function likePatternPrependsWildcardWhenAnywhereIsTrue(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: ['foo'], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig(anywhere: true));

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['%foo']]], self::conditionsOf($listing));
    }

    #[Test]
    public function likePatternHasNoLeadingWildcardWhenAnywhereIsFalse(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: ['foo'], notLikes: []);

        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig(anywhere: false));

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['foo']]], self::conditionsOf($listing));
    }

    #[Test]
    public function likeMarkerBecomesAWildcardWhileLiteralWildcardsStayEscaped(): void
    {
        $listing = new SpyListing();

        (new SearchTermsQuery())->applyString($listing, 'name', '100%*', new SearchTermsConfig());

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['%100~%%']]], self::conditionsOf($listing));
    }

    #[Test]
    public function aMarkerThatSqlHelperEscapesStillBecomesAWildcard(): void
    {
        $listing = new SpyListing();

        // The marker is `%`, which SqlHelper also escapes — escaping before substituting would
        // turn the caller's wildcard into a literal that can never match.
        (new SearchTermsQuery())->applyString($listing, 'name', '100%done', new SearchTermsConfig(likeMarkers: ['%']));

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['%100%done']]], self::conditionsOf($listing));
    }

    #[Test]
    public function severalLikeMarkersAllMapToTheSameWildcard(): void
    {
        $listing = new SpyListing();

        (new SearchTermsQuery())->applyString($listing, 'name', 'a*b?c', new SearchTermsConfig(likeMarkers: ['*', '?']));

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['%a%b%c']]], self::conditionsOf($listing));
    }

    #[Test]
    public function aMultiCharacterMarkerMapsToOneWildcardAndTheRestOfTheValueSurvives(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: ['a**b'], notLikes: []);

        // A marker longer than one character is what makes splitOnMarkers()'s $skip counter reachable
        // at all — with single-character markers it stays 0 and no mutation of it can be killed.
        (new SearchTermsQuery())->apply(
            $listing,
            'name',
            $parsed,
            new SearchTermsConfig(anywhere: false, likeMarkers: ['**']),
        );

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['a%b']]], self::conditionsOf($listing));
    }

    #[Test]
    public function aMarkerThatPrefixesAnotherNeverShadowsTheLongerOne(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: ['a**b*c'], notLikes: []);

        // SearchTermsConfig normalises markers longest-first, which is the only reason taking the first
        // hit in markerAt() is correct.
        (new SearchTermsQuery())->apply(
            $listing,
            'name',
            $parsed,
            new SearchTermsConfig(anywhere: false, likeMarkers: ['*', '**']),
        );

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['a%b%c']]], self::conditionsOf($listing));
    }

    #[Test]
    public function withNoLikeMarkersConfiguredTheParserNeverProducesALikeTerm(): void
    {
        $listing = new SpyListing();

        (new SearchTermsQuery())->applyString($listing, 'name', 'a*b', new SearchTermsConfig(likeMarkers: []));

        self::assertSame([['(name = ?)', ['a*b']]], self::conditionsOf($listing));
    }

    #[Test]
    public function withNoLikeMarkersConfiguredAHandBuiltLikeTermStaysFullyEscaped(): void
    {
        $listing = new SpyListing();
        $parsed = new ParsedSearchTerms(equals: [], notEquals: [], likes: ['a*b'], notLikes: []);

        // Reachable only through apply(), not applyString(): with no markers the parser can never
        // put a term in the likes bucket, but a caller assembling ParsedSearchTerms itself can.
        (new SearchTermsQuery())->apply($listing, 'name', $parsed, new SearchTermsConfig(likeMarkers: []));

        self::assertSame([["(name LIKE ? ESCAPE '~')", ['%a*b']]], self::conditionsOf($listing));
    }

    #[Test]
    public function bangNegatesJustLikeDash(): void
    {
        $listing = new SpyListing();

        (new SearchTermsQuery())->applyString($listing, 'name', '!dog', new SearchTermsConfig());

        self::assertSame([['((name != ? OR name IS NULL))', ['dog']]], self::conditionsOf($listing));
    }

    #[Test]
    public function applyStringParsesThenApplies(): void
    {
        $listing = new SpyListing();

        (new SearchTermsQuery())->applyString($listing, 'name', 'dog', new SearchTermsConfig());

        self::assertSame([['(name = ?)', ['dog']]], self::conditionsOf($listing));
    }

    #[Test]
    public function anUntaggedDuplicateOnTheSameColumnIsRejected(): void
    {
        $listing = new SpyListing();
        $query = new SearchTermsQuery();
        $query->applyString($listing, 'name', 'dog', new SearchTermsConfig());

        $this->expectException(DuplicateConditionException::class);
        $this->expectExceptionMessage('identical condition on column "name"');

        // Without the guard Pimcore would key both groups as `((name = ?))` and 'dog' would be
        // replaced by 'cat' with no error at all.
        $query->applyString($listing, 'name', 'cat', new SearchTermsConfig());
    }

    #[Test]
    public function aRejectedDuplicateLeavesTheListingExactlyAsItWas(): void
    {
        $listing = new SpyListing();
        $query = new SearchTermsQuery();
        $query->applyString($listing, 'name', 'dog', new SearchTermsConfig());

        try {
            $query->applyString($listing, 'name', 'cat', new SearchTermsConfig());
            self::fail('Expected a DuplicateConditionException.');
        } catch (DuplicateConditionException) {
            self::assertSame([['(name = ?)', ['dog']]], self::conditionsOf($listing));
        }
    }

    #[Test]
    public function distinctTagsLetTwoSearchesShareOneColumn(): void
    {
        $listing = new SpyListing();
        $query = new SearchTermsQuery();

        $query->applyString($listing, 'name', 'dog', new SearchTermsConfig(), 'first');
        $query->applyString($listing, 'name', 'cat', new SearchTermsConfig(), 'second');

        self::assertSame(
            [['(name = ?) /* first */', ['dog']], ['(name = ?) /* second */', ['cat']]],
            self::conditionsOf($listing),
        );
    }

    #[Test]
    public function reusingTheSameTagIsStillRejected(): void
    {
        $listing = new SpyListing();
        $query = new SearchTermsQuery();
        $query->applyString($listing, 'name', 'dog', new SearchTermsConfig(), 'same');

        $this->expectException(DuplicateConditionException::class);
        $this->expectExceptionMessage('tagged "same"');

        $query->applyString($listing, 'name', 'cat', new SearchTermsConfig(), 'same');
    }

    #[Test]
    public function differentColumnsNeedNoTag(): void
    {
        $listing = new SpyListing();
        $query = new SearchTermsQuery();

        $query->applyString($listing, 'name', 'dog', new SearchTermsConfig());
        $query->applyString($listing, 'title', 'dog', new SearchTermsConfig());

        self::assertSame(
            [['(name = ?)', ['dog']], ['(title = ?)', ['dog']]],
            self::conditionsOf($listing),
        );
    }

    #[Test]
    #[DataProvider('invalidTagProvider')]
    public function aTagThatCouldEscapeTheSqlCommentIsRejected(string $tag): void
    {
        $listing = new SpyListing();

        $this->expectException(InvalidConditionTagException::class);
        $this->expectExceptionMessage(\sprintf('letters, digits, underscore, dot or hyphen, "%s" given', $tag));

        (new SearchTermsQuery())->applyString($listing, 'name', 'dog', new SearchTermsConfig(), $tag);
    }

    #[Test]
    public function anInvalidTagIsRejectedBeforeAnythingIsAddedToTheListing(): void
    {
        $listing = new SpyListing();

        try {
            (new SearchTermsQuery())->applyString($listing, 'name', 'dog', new SearchTermsConfig(), 'a b');
            self::fail('Expected an InvalidConditionTagException.');
        } catch (InvalidConditionTagException) {
            self::assertSame([], self::conditionsOf($listing));
        }
    }

    /**
     * Pimcore keys each condition by the fragment it has itself wrapped in one more pair of
     * parentheses — stripped here so the assertions read like the fragment the query emitted.
     *
     * @return list<array{string, mixed}>
     */
    private static function conditionsOf(SpyListing $listing): array
    {
        /** @var array<string, array{value: mixed, concatenator: string, ignore-value: bool}> $conditionParams */
        $conditionParams = $listing->getConditionParams();

        $conditions = [];

        foreach ($conditionParams as $fragment => $param) {
            $conditions[] = [substr($fragment, 1, -1), $param['value']];
        }

        return $conditions;
    }
}
