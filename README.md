# pimbay/search-query-pimcore

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pimbay/search-query-pimcore?style=flat-square&color=blue)](https://packagist.org/packages/pimbay/search-query-pimcore)
[![PHP Version](https://img.shields.io/badge/php-%3E%3D8.3-8892bf?style=flat-square&logo=php)](https://php.net)
[![License](https://img.shields.io/packagist/l/pimbay/search-query-pimcore?style=flat-square&color=green)](LICENSE)
[![Code Coverage](https://img.shields.io/badge/coverage-100%25-brightgreen?style=flat-square)](https://codeberg.org/pimbay-php/search-query-pimcore)
[![Mutation Score](https://img.shields.io/badge/MSI-100%25-brightgreen?style=flat-square)](https://codeberg.org/pimbay-php/search-query-pimcore)

A Pimcore Listing adapter for [`pimbay/search-query`](https://packagist.org/packages/pimbay/search-query).
`Adapter\PimcoreListingAdapter` implements `PageAdapter`/`SliceAdapter`/`CountableAdapter`/`HeadableAdapter`/`AllAdapter`/`IdentifiableAdapter` — all at once — over a Pimcore listing.
`SearchTerms\SearchTermsQuery` turns an already-parsed `SearchTerms\ParsedSearchTerms` (from `pimbay/search-query`'s `SearchTermsParser`) into `addConditionParam()` calls — free-text search wired into the same listing.

Supports Pimcore `^11.5 || ^12.0 || ^2026.0`.
`11.5` rather than `11.0` is the floor because 11.0 and 11.1 cap at PHP 8.2, which this package does not support, and 11.5 is the line still in use.
Those majors are **not licensed alike** — 11.x is GPLv3/PCL, 12.x and newer are the Pimcore Open Core License with a revenue threshold. See [docs/THIRD-PARTY-NOTICES.md](docs/THIRD-PARTY-NOTICES.md).

## Installation

```bash
composer require pimbay/search-query-pimcore
```

## Usage

### `Adapter\PimcoreListingAdapter`

A single adapter covers every capability — unlike Doctrine, Pimcore listings have no fetch-join row-multiplication problem, so there's no split into separate adapter classes.

It accepts any of Pimcore's six concrete listing classes: `DataObject\Listing`, `Asset\Listing`, `Document\Listing`, `Element\Note\Listing`, `Element\Tag\Listing` and `Version\Listing`, or a subclass of one of them such as a generated `DataObject\Product\Listing`.
It deliberately does **not** accept a bare `Pimcore\Model\Listing\AbstractListing`: the methods this adapter needs are not declared there — see `docs/DECISIONS.md`.

```php
<?php

declare(strict_types=1);

use PimBay\SearchQuery\Page\PageAssembler;
use PimBay\SearchQuery\Pimcore\Adapter\PimcoreListingAdapter;

$listing = new DataObject\Product\Listing();
$listing->setOrderKey('id');
$listing->setOrder('DESC');

$adapter = new PimcoreListingAdapter($listing);

$result = (new PageAssembler())->paginate($adapter, 1, 20);
```

`ids()` needs no field name to configure — the listing's own `loadIdList()` already knows how to resolve the identifier:

```php
$adapter->ids(); // int[]
```

`count()`, `ids()` and `all()` are whole-set reads: each resets the listing's offset and limit on its own clone first, so a window left over from an earlier paginated read cannot silently bound them.
`head()` does the same and applies its own limit.
Your listing is never mutated — every call works on a clone.

### `SearchTerms\SearchTermsQuery`

Turns a `SearchTerms\ParsedSearchTerms` into `addConditionParam()` calls against one column.

```php
<?php

declare(strict_types=1);

use PimBay\SearchQuery\Pimcore\SearchTerms\SearchTermsQuery;
use PimBay\SearchQuery\SearchTerms\SearchTermsConfig;
use PimBay\SearchQuery\SearchTerms\SearchTermsParser;

$config = new SearchTermsConfig();
$parsed = (new SearchTermsParser())->parse(['dog', 'hors*', '-cow'], $config);

(new SearchTermsQuery())->apply($listing, 'title', $parsed, $config);
```

Negated terms (`-cow` above) also keep records whose column is `NULL`, because `column != 'cow'` is `NULL` rather than `TRUE` for those records and `WHERE` keeps only `TRUE` — so the stricter reading silently drops every record with no value.
Pass `new SearchTermsConfig(ignoredTermsMatchNull: false)` for that stricter reading.

> **Security note:** `$column` (here and in `applyString()`) is interpolated directly into the generated SQL fragment — only the parsed *values* go through `addConditionParam()`'s bound parameters. Only ever pass a literal from your own code (or an allowlist you control); never pass a raw, unvalidated end-user string as `$column`, or it opens a SQL injection path through the column name itself.

#### Applying more than one search to the same column

`AbstractListing::addConditionParam()` keys its conditions by the SQL fragment string, so a second, identically shaped condition on the same column would silently replace the first instead of being added.
`apply()`/`applyString()` detect that and throw `Exception\DuplicateConditionException` rather than let a filter disappear.

Pass a `$tag` when two searches on one column are what you actually want. It is appended as an inert SQL comment, which makes the fragments distinct:

```php
$query = new SearchTermsQuery();

$query->applyString($listing, 'title', 'dog', $config, 'first');   // (title = ?) /* first */
$query->applyString($listing, 'title', 'cat', $config, 'second');  // (title = ?) /* second */
```

`$tag` is whitelisted against `/^[\w.-]+$/` and throws `Exception\InvalidConditionTagException` otherwise — it lands in raw SQL, so it is not escaped.

There is no `$paramPrefix` parameter as in `search-query-doctrine` — `addConditionParam()` takes positional `?` placeholders, so there is nothing to namespace; `$tag` does the disambiguating job instead.

## Testing

```bash
composer test:83-pimcore11     # PHP 8.3 + pimcore/pimcore ^11.5
composer test:83-pimcore12     # PHP 8.3 + pimcore/pimcore ^12.0
composer test:84-pimcore12     # PHP 8.4 + pimcore/pimcore ^12.0
composer test:84-pimcore2026   # PHP 8.4 + pimcore/pimcore ^2026.0
composer test:85-pimcore2026   # PHP 8.5 + pimcore/pimcore ^2026.0
composer test:all              # all of the above
composer test:coverage         # php83-pimcore12 combo, --coverage-text
composer test:mutation         # infection — mutation testing, --min-msi=100 --min-covered-msi=100
```

Each combo runs in its own Docker image with dependencies baked in at build time.
Requires Docker and Docker Compose locally.

`tests/Unit/` only — the listing is a hand-written `SpyListing` fixture extending the real `DataObject\Listing`, not a PHPUnit mock and not a running Pimcore installation. See `docs/DECISIONS.md` for both.

| PHP | Pimcore 11.x | Pimcore 12.x | Pimcore 2026.x |
|:----|:----|:----|:----|
| **8.3** | ✅ | ✅ | — |
| **8.4** | — | ✅ | ✅ |
| **8.5** | — | — | ✅ |

## Development Helpers

```bash
composer php:cs        # php-cs-fixer, --dry-run --diff (check only)
composer php:cs:fix    # same, applies the fix
composer php:stan      # phpstan analyse
```

## Architecture & Decisions

- **[docs/context.md](docs/context.md)** — current working state: what's in progress, what's next.
- **[docs/DECISIONS.md](docs/DECISIONS.md)** — why things are built the way they are, in the order the decisions were made.
- **[docs/CHANGELOG.md](docs/CHANGELOG.md)** — version history.

## Packages in the stack

| Package | Description |
|---|---|
| `pimbay/search-query` | Framework-agnostic contracts this package adapts Pimcore to — no datasource code of its own. |
| `pimbay/search-query-doctrine` | Adapters over a Doctrine DBAL or ORM QueryBuilder — the sibling package for non-Pimcore projects. |
| `pimbay/search-query-pimcore` | This package — adapters over a Pimcore listing. |

## License

Public domain — [Unlicense](LICENSE)

Created by [Jan Sarmir](https://pimbay.dev) · No conditions · No copyright

Bundled third-party dependencies and their licenses: **[docs/THIRD-PARTY-NOTICES.md](docs/THIRD-PARTY-NOTICES.md)**.
