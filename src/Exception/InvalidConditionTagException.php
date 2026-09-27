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

final class InvalidConditionTagException extends SearchQueryException
{
    public function __construct(string $tag)
    {
        parent::__construct(\sprintf(
            'Tag must consist of letters, digits, underscore, dot or hyphen, "%s" given.',
            $tag,
        ));
    }
}
