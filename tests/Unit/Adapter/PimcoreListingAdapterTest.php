<?php

declare(strict_types=1);

namespace PimBay\SearchQuery\Pimcore\Tests\Unit\Adapter;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use PimBay\SearchQuery\Pimcore\Adapter\PimcoreListingAdapter;
use PimBay\SearchQuery\Pimcore\Tests\Fixture\SpyListing;
use PimBay\SearchQuery\Pimcore\Tests\Fixture\SpyTagListing;
use Pimcore\Model\DataObject;

final class PimcoreListingAdapterTest extends TestCase
{
    /**
     * @return iterable<string, array{int, int, bool}>
     */
    public static function pageSliceProvider(): iterable
    {
        yield 'lookahead row available signals more' => [2, 3, true];
        yield 'exactly full signals no more' => [2, 2, false];
        yield 'partial signals no more' => [2, 1, false];
        yield 'empty signals no more' => [2, 0, false];
    }

    #[Test]
    public function countUsesGetTotalCountNotGetCount(): void
    {
        // SpyListing::getCount() throws — reaching for it here would fail the test rather than
        // silently return a wrong number. See docs/DECISIONS.md.
        $listing = new SpyListing(totalCount: 5);

        self::assertSame(5, (new PimcoreListingAdapter($listing))->count());
    }

    #[Test]
    public function countIgnoresAWindowAlreadySetOnTheCallersListing(): void
    {
        $listing = new SpyListing(totalCount: 5);
        $listing->setOffset(10)->setLimit(2);

        self::assertSame(5, (new PimcoreListingAdapter($listing))->count());
        self::assertSame([10, 0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([2, null], $listing->log->argumentsOf('setLimit'));
    }

    #[Test]
    public function idsDelegatesToLoadIdList(): void
    {
        $listing = new SpyListing(idList: [1, 2, 3]);

        self::assertSame([1, 2, 3], (new PimcoreListingAdapter($listing))->ids());
    }

    #[Test]
    public function idsIgnoresAWindowAlreadySetOnTheCallersListing(): void
    {
        $listing = new SpyListing(idList: [1, 2, 3]);
        // loadIdList() honours offset and limit, so without the reset this would return one ID.
        $listing->setOffset(10)->setLimit(2);

        self::assertSame([1, 2, 3], (new PimcoreListingAdapter($listing))->ids());
        self::assertSame([10, 0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([2, null], $listing->log->argumentsOf('setLimit'));
    }

    #[Test]
    public function headSetsOffsetZeroAndTheGivenSize(): void
    {
        $rows = self::rows(2);
        $listing = new SpyListing($rows);

        self::assertSame($rows, (new PimcoreListingAdapter($listing))->head(2));
        self::assertSame([0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([2], $listing->log->argumentsOf('setLimit'));
    }

    #[Test]
    public function allResetsTheWindowSoAPreSetOneCannotBoundTheRead(): void
    {
        $rows = self::rows(3);
        $listing = new SpyListing($rows);
        // Without the reset this window would silently make the unbounded read a bounded one.
        $listing->setOffset(10)->setLimit(2);

        self::assertSame($rows, (new PimcoreListingAdapter($listing))->all());

        self::assertSame([10, 0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([2, null], $listing->log->argumentsOf('setLimit'));
        self::assertSame(['setOffset', 'setLimit', 'setOffset', 'setLimit', 'load'], $listing->log->methods());
        self::assertSame(10, $listing->getOffset());
        self::assertSame(2, $listing->getLimit());
    }

    #[Test]
    public function pageViewReturnsResultsWithTotalCount(): void
    {
        $rows = self::rows(2);
        $listing = new SpyListing($rows, totalCount: 5);

        $chunk = (new PimcoreListingAdapter($listing))->pageView(2, 2);

        self::assertSame($rows, $chunk->results);
        self::assertSame(5, $chunk->totalCount);
        self::assertSame([2, 0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([2, null], $listing->log->argumentsOf('setLimit'));
    }

    #[Test]
    #[DataProvider('pageSliceProvider')]
    public function pageSliceDropsTheLookaheadRowAndSignalsHasMore(int $size, int $loadedCount, bool $expectedHasMore): void
    {
        $rows = self::rows($loadedCount);
        $listing = new SpyListing($rows);

        $chunk = (new PimcoreListingAdapter($listing))->pageSlice(4, $size);

        self::assertSame($expectedHasMore, $chunk->hasMore);
        self::assertSame(\array_slice($rows, 0, $size), $chunk->results);
        self::assertSame([4], $listing->log->argumentsOf('setOffset'));
        self::assertSame([$size + 1], $listing->log->argumentsOf('setLimit'));
    }

    #[Test]
    public function repeatedCallsAreIndependentBecauseEachOneClonesTheListing(): void
    {
        $listing = new SpyListing(self::rows(2), totalCount: 2);
        $adapter = new PimcoreListingAdapter($listing);

        $adapter->pageSlice(0, 3);
        $adapter->pageSlice(30, 5);

        self::assertSame([0, 30], $listing->log->argumentsOf('setOffset'));
        self::assertSame([4, 6], $listing->log->argumentsOf('setLimit'));
        self::assertSame(0, $listing->getOffset());
        self::assertNull($listing->getLimit());
    }

    #[Test]
    public function eachCallOperatesOnAFreshCloneNotTheOriginalListing(): void
    {
        $listing = new SpyListing(self::rows(1), totalCount: 1);

        (new PimcoreListingAdapter($listing))->pageView(20, 5);

        self::assertSame([20, 0], $listing->log->argumentsOf('setOffset'));
        self::assertSame([5, null], $listing->log->argumentsOf('setLimit'));
        self::assertSame(0, $listing->getOffset());
        self::assertNull($listing->getLimit());
    }

    #[Test]
    public function theCloneKeepsTheCallbackRegisteredThroughOnCreateQueryBuilder(): void
    {
        $callback = static function (): void {
        };
        $listing = new SpyListing(totalCount: 5);
        $listing->onCreateQueryBuilder($callback);

        (new PimcoreListingAdapter($listing))->count();

        self::assertSame([self::hook($callback)], $listing->log->argumentsOf('daoHook'));
    }

    #[Test]
    public function theCloneKeepsEveryQueryBuilderProcessor(): void
    {
        if (!SpyListing::hasQueryBuilderProcessors()) {
            self::markTestSkipped('Query builder processors exist from Pimcore 12.2.');
        }

        $first = static function (): void {
        };
        $second = static function (): void {
        };
        $listing = new SpyListing(totalCount: 5);
        $listing->addQueryBuilderProcessor($first);
        $listing->addQueryBuilderProcessor($second);

        (new PimcoreListingAdapter($listing))->count();

        self::assertSame([[$first, $second]], $listing->log->argumentsOf('daoHook'));
    }

    #[Test]
    public function eachCallCopiesTheHookAsItIsAtThatMoment(): void
    {
        $callback = static function (): void {
        };
        $listing = new SpyListing(totalCount: 5);
        $listing->onCreateQueryBuilder($callback);
        $adapter = new PimcoreListingAdapter($listing);

        $adapter->count();
        $listing->onCreateQueryBuilder(null);
        $adapter->count();

        self::assertSame([self::hook($callback), self::hook()], $listing->log->argumentsOf('daoHook'));
    }

    #[Test]
    public function aListingWithoutADaoYetIsClonedWithoutCreatingOne(): void
    {
        $listing = new SpyListing(totalCount: 5);

        (new PimcoreListingAdapter($listing))->count();

        self::assertSame([], $listing->log->argumentsOf('daoCallback'));
    }

    #[Test]
    public function aListingWhoseDaoHasNoQueryBuilderHooksStillClones(): void
    {
        $listing = new SpyTagListing();
        $listing->getDao();

        self::assertSame(7, (new PimcoreListingAdapter($listing))->count());
    }

    /**
     * What the DAO holds after `onCreateQueryBuilder()`: a list of processors from Pimcore 12.2, one callback before.
     */
    private static function hook(?callable $callback = null): mixed
    {
        if (SpyListing::hasQueryBuilderProcessors()) {
            return null === $callback ? [] : [$callback];
        }

        return $callback;
    }

    /**
     * @return list<DataObject>
     */
    private static function rows(int $count): array
    {
        return array_map(static fn (): DataObject => new DataObject\Folder(), array_fill(0, $count, null));
    }
}
