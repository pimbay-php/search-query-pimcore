# Changelog

All notable changes to this project are documented here.
Format follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/); versioning follows [SemVer](https://semver.org/).

## [Unreleased]

### Added
- `SqlHelper::contains()`, `startsWith()` and `endsWith()` build the `%…%`, `…%` and `%…` `LIKE` pattern for a value with its own `%`, `_` and escape character escaped.

### Fixed
- `Adapter\PimcoreListingAdapter` keeps the joins and selects added through `onCreateQueryBuilder()`; reads ran on a clone without them, so a query on a joined column failed with `Unknown column`.

## [1.0.0] - 2026-09-29

### Added
- `Adapter\PimcoreListingAdapter` — implements `pimbay/search-query`'s `PageAdapter`, `SliceAdapter`, `CountableAdapter`, `HeadableAdapter`, `AllAdapter` and `IdentifiableAdapter` all at once over a Pimcore listing. Accepts any of Pimcore's six concrete listing classes (`DataObject`, `Asset`, `Document`, `Element\Note`, `Element\Tag`, `Version`), not a bare `AbstractListing` — the methods the adapter needs are not declared there.
- `count()`, `ids()` and `all()` reset the listing's offset and limit before reading, so a window left over from an earlier paginated read cannot silently bound a whole-set read — `loadIdList()` in particular applies both. `head()` does the same with its own limit, and `pageView()` takes its total from `count()`.
- `SearchTerms\SearchTermsQuery` — turns a `SearchTerms\ParsedSearchTerms` into `addConditionParam()` calls against one column, OR-grouping the positive terms and AND-grouping the negative ones.
- `SearchTerms\SearchTermsQuery` rejects a condition group the listing already holds, with `Exception\DuplicateConditionException`. `AbstractListing::addConditionParam()` keys conditions by their SQL fragment, so an identically shaped second search on one column would otherwise replace the first and drop a filter with no error.
- An optional `$tag` on `apply()`/`applyString()` disambiguates two searches on the same column by appending an inert SQL comment to the fragment. Whitelisted against `/^[\w.-]+$/`, otherwise `Exception\InvalidConditionTagException`.
- `SearchTermsQuery`'s marker scan, `LIKE` pattern building and `NULL`-aware negation are byte-identical to `search-query-doctrine`'s, so a term behaves the same whichever package runs it.
- Support for `pimcore/pimcore` `^2026.0` alongside `^11.5` and `^12.0`, and for PHP 8.5. The 11.x floor is `11.5`; 11.0 and 11.1 cap at PHP 8.2.

### Notes
- `pimcore/pimcore` is licensed differently per major: `11.x` is GPLv3/PCL, while `12.x` and `2026.x` are the Pimcore Open Core License, whose free production use is capped by a revenue threshold. See [THIRD-PARTY-NOTICES.md](THIRD-PARTY-NOTICES.md).
