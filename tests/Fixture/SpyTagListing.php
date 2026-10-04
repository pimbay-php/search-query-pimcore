<?php

declare(strict_types=1);

namespace PimBay\SearchQuery\Pimcore\Tests\Fixture;

use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\Element\Tag;

/**
 * A listing whose DAO has no `onCreateQueryBuilder()` hook, like the Note and Version ones.
 */
final class SpyTagListing extends Tag\Listing
{
    public function initDao(?string $key = null, bool $forceDetection = false): void
    {
        $class = self::locateDaoClass(Tag\Listing::class) ?? throw new \LogicException('No DAO class found.');
        $dao = new $class();

        if (!$dao instanceof AbstractDao) {
            throw new \LogicException('Not a DAO.');
        }

        $this->setDao($dao->setModel($this));
    }

    public function getTotalCount(): int
    {
        return 7;
    }
}
