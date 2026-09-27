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

namespace PimBay\SearchQuery\Pimcore\Exception;

use PimBay\SearchQuery\Exception\SearchQueryException;

final class DuplicateConditionException extends SearchQueryException
{
    public function __construct(string $column, ?string $tag)
    {
        parent::__construct(\sprintf(
            'Listing already holds an identical condition on column "%s"%s; Pimcore would silently replace it. Pass a distinct $tag to keep both.',
            $column,
            null === $tag ? '' : \sprintf(' tagged "%s"', $tag),
        ));
    }
}
