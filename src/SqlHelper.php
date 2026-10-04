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

namespace PimBay\SearchQuery\Pimcore;

final class SqlHelper
{
    /**
     * Deliberately not a backslash and deliberately not configurable: MySQL drops its implicit `LIKE`
     * escape character under `NO_BACKSLASH_ESCAPES`, and then matches nothing.
     */
    private const string LIKE_ESCAPE_CHAR = '~';

    /**
     * Neutralizes a literal escape character first, then escapes `_`/`%` — reversing the order
     * would double-escape an escape character already present in the value.
     */
    public static function escapeLike(string $like): string
    {
        $escaped = str_replace(self::LIKE_ESCAPE_CHAR, self::LIKE_ESCAPE_CHAR.self::LIKE_ESCAPE_CHAR, $like);

        return str_replace(['_', '%'], [self::LIKE_ESCAPE_CHAR.'_', self::LIKE_ESCAPE_CHAR.'%'], $escaped);
    }

    /**
     * Emitted with every `LIKE`, so the escaping above never depends on the server's `sql_mode`.
     */
    public static function likeEscapeClause(): string
    {
        return \sprintf("ESCAPE '%s'", self::LIKE_ESCAPE_CHAR);
    }

    public static function contains(string $value): string
    {
        return '%'.self::escapeLike($value).'%';
    }

    public static function startsWith(string $value): string
    {
        return self::escapeLike($value).'%';
    }

    public static function endsWith(string $value): string
    {
        return '%'.self::escapeLike($value);
    }
}
