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

namespace PimBay\SearchQuery\Pimcore\Adapter;

use PimBay\SearchQuery\Adapter\AllAdapter;
use PimBay\SearchQuery\Adapter\CountableAdapter;
use PimBay\SearchQuery\Adapter\HeadableAdapter;
use PimBay\SearchQuery\Adapter\IdentifiableAdapter;
use PimBay\SearchQuery\Page\PageAdapter;
use PimBay\SearchQuery\Page\PageChunk;
use PimBay\SearchQuery\Slice\SliceAdapter;
use PimBay\SearchQuery\Slice\SliceChunk;
use Pimcore\Model\Asset\Listing as AssetListing;
use Pimcore\Model\DataObject\Listing as DataObjectListing;
use Pimcore\Model\Document\Listing as DocumentListing;
use Pimcore\Model\Element\Note\Listing as NoteListing;
use Pimcore\Model\Element\Tag\Listing as TagListing;
use Pimcore\Model\Version\Listing as VersionListing;

/**
 * One adapter covers every capability interface — Pimcore listings have no fetch-join row-multiplication problem.
 *
 * @template T of object
 *
 * @implements PageAdapter<T>
 * @implements SliceAdapter<T>
 * @implements HeadableAdapter<T>
 * @implements AllAdapter<T>
 * @implements IdentifiableAdapter<int>
 */
final readonly class PimcoreListingAdapter implements PageAdapter, SliceAdapter, CountableAdapter, HeadableAdapter, AllAdapter, IdentifiableAdapter
{
    public function __construct(
        private AssetListing|DataObjectListing|DocumentListing|NoteListing|TagListing|VersionListing $listing,
    ) {
    }

    public function count(): int
    {
        // getTotalCount() resets the window itself on all six listing classes; resetting here anyway
        // keeps the count independent of that staying true.
        return $this->cloneListing()
            ->setOffset(0)
            ->setLimit(null)
            ->getTotalCount();
    }

    public function ids(): array
    {
        // loadIdList() applies the listing's offset and limit, so a window left over from an earlier
        // paginated read would truncate an ID list whose contract is the whole set.
        return $this->cloneListing()
            ->setOffset(0)
            ->setLimit(null)
            ->loadIdList();
    }

    public function head(int $size): iterable
    {
        /** @var list<T> $results */
        $results = $this->cloneListing()
            ->setOffset(0)
            ->setLimit($size)
            ->load();

        return $results;
    }

    public function all(): iterable
    {
        // AllAdapter is the unbounded read by contract — a window already set on the caller's listing
        // would quietly turn it into a bounded one that the caller of all() cannot see.
        /** @var list<T> $results */
        $results = $this->cloneListing()
            ->setOffset(0)
            ->setLimit(null)
            ->load();

        return $results;
    }

    public function pageView(int $offset, int $size): PageChunk
    {
        /** @var list<T> $results */
        $results = $this->cloneListing()
            ->setOffset($offset)
            ->setLimit($size)
            ->load();

        // count() counts on a clone of its own, so the total never depends on the window this page set.
        return new PageChunk($results, $this->count());
    }

    public function pageSlice(int $offset, int $size): SliceChunk
    {
        /** @var list<T> $results */
        $results = $this->cloneListing()
            ->setOffset($offset)
            ->setLimit($size + 1)
            ->load();

        $hasMore = \count($results) > $size;

        if ($hasMore) {
            array_pop($results);
        }

        return new SliceChunk($results, $hasMore);
    }

    private function cloneListing(): AssetListing|DataObjectListing|DocumentListing|NoteListing|TagListing|VersionListing
    {
        return clone $this->listing;
    }
}
