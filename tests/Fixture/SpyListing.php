<?php

declare(strict_types=1);

namespace PimBay\SearchQuery\Pimcore\Tests\Fixture;

use Pimcore\Model\Dao\AbstractDao;
use Pimcore\Model\DataObject;

/**
 * A real `DataObject\Listing` with its DB-facing methods stubbed out and its mutators logged.
 *
 * Not a PHPUnit double: `createMock()` cannot configure a method that exists only as a Pimcore
 * `@method` annotation. See docs/DECISIONS.md.
 */
final class SpyListing extends DataObject\Listing
{
    /**
     * Held by reference through `clone`, so a test can observe calls the adapter made on a copy
     * it never handed back.
     */
    public CallLog $log;

    /**
     * @param list<DataObject> $rows
     * @param list<int> $idList
     */
    public function __construct(
        private readonly array $rows = [],
        private readonly int $totalCount = 0,
        private readonly array $idList = [],
    ) {
        $this->log = new CallLog();
    }

    /**
     * The processors list replaces the single callback from Pimcore 12.2.
     */
    public static function hasQueryBuilderProcessors(): bool
    {
        $class = self::locateDaoClass(DataObject\Listing::class);

        return null !== $class && property_exists($class, 'queryBuilderProcessors');
    }

    /**
     * Skips `configure()`, which would open a database connection.
     */
    public function initDao(?string $key = null, bool $forceDetection = false): void
    {
        $class = self::locateDaoClass(DataObject\Listing::class) ?? throw new \LogicException('No DAO class found.');
        $dao = new $class();

        if (!$dao instanceof AbstractDao) {
            throw new \LogicException('Not a DAO.');
        }

        $this->setDao($dao->setModel($this));
    }

    public function setOffset(int $offset): static
    {
        $this->log->record('setOffset', $offset);

        return parent::setOffset($offset);
    }

    public function setLimit(?int $limit): static
    {
        $this->log->record('setLimit', $limit);

        return parent::setLimit($limit);
    }

    public function addConditionParam(string $condition, mixed $value = null, string $concatenator = 'AND'): static
    {
        $this->log->record('addConditionParam', [$condition, $value]);

        return parent::addConditionParam($condition, $value, $concatenator);
    }

    /**
     * @return list<DataObject>
     */
    public function load(): array
    {
        $this->log->record('load');

        return $this->rows;
    }

    /**
     * @return list<int>
     */
    public function loadIdList(): array
    {
        return $this->idList;
    }

    public function getTotalCount(): int
    {
        if ($this->dao instanceof AbstractDao) {
            $hook = self::hasQueryBuilderProcessors() ? 'queryBuilderProcessors' : 'onCreateQueryBuilderCallback';
            $this->log->record('daoHook', self::daoProperty($this->dao, $hook));
        }

        return $this->totalCount;
    }

    /**
     * A trap, not a stub — `getCount()` is unreliable once `setLimit()`/`setOffset()` have been
     * applied, so the adapter must never reach for it. See docs/DECISIONS.md.
     */
    public function getCount(): int
    {
        throw new \LogicException('getCount() must never be called — use getTotalCount().');
    }

    /**
     * The hooks have no public getter.
     */
    private static function daoProperty(AbstractDao $dao, string $name): mixed
    {
        if (!property_exists($dao, $name)) {
            return null;
        }

        return (new \ReflectionProperty($dao, $name))->getValue($dao);
    }
}
