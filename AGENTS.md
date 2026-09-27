# AGENTS.md — search-query-pimcore

## Project Overview

`pimbay/search-query-pimcore` implements `pimbay/search-query`'s adapter contracts over a Pimcore `AbstractListing` — a single `PimcoreListingAdapter` covers every capability interface at once, unlike `search-query-doctrine`'s `Simple`/`Identity`/`FetchJoinSafe` split.
`pimcore/pimcore` required directly — `AbstractListing` is a concrete class, not an interface, so there's no way to type-hint against it without the real package.
License: Unlicense. Minimum PHP: 8.3.

## Commands

```bash
composer install
composer php:cs               # php-cs-fixer, --dry-run --diff (check only, never mutates)
composer php:cs:fix           # same, applies the fix
composer php:stan             # phpstan analyse, level: max — src and tests, one config each
composer test:83-pimcore11    # docker compose run — PHP 8.3 + pimcore/pimcore ^11.5
composer test:83-pimcore12    # docker compose run — PHP 8.3 + pimcore/pimcore ^12.0
composer test:84-pimcore12    # docker compose run — PHP 8.4 + pimcore/pimcore ^12.0
composer test:84-pimcore2026  # docker compose run — PHP 8.4 + pimcore/pimcore ^2026.0
composer test:85-pimcore2026  # docker compose run — PHP 8.5 + pimcore/pimcore ^2026.0
composer test:all             # all test:*-pimcore* combos
composer test:coverage        # docker compose run, php83-pimcore12 combo — phpunit --coverage-text
composer test:mutation        # infection — mutation testing, --min-msi=100 --min-covered-msi=100
composer ci                   # php:cs + php:stan + test:all + test:mutation
```

## Code Style

- **PHP 8.3+**, `declare(strict_types=1)` everywhere.
- **`@PER-CS2.0` + `@PER-CS2.0:risky` + `@PHP83Migration` + `@Symfony` + `@Symfony:risky`** via php-cs-fixer — run `composer php:cs:fix`, don't hand-format.
- **`final` by default**; remove only with a stated, repo-specific reason.
- **`readonly` properties** by default — promoted constructor properties over separate declaration + assignment.
- **PSR-4**, one class per file, namespace mirrors directory 1:1.
- **Comments** only where they explain a non-trivial decision or _why_ — never restate _what_ the code already says. Don't comment obvious lines. Keep to 1-2 lines; more only for genuinely complex logic. Always in English. Wrap at 120 columns.
- **Markdown** (`.md` only): semantic linebreaks — break at sentence end, never inside a list item.
- **Docs discipline**: no "Project Layout" in READMEs — the tree speaks for itself.

## Architecture

```
src/
  Adapter/        — pimbay/search-query adapter contracts implemented over Pimcore\Model\Listing\AbstractListing.
  SearchTerms/    — turns a pimbay/search-query ParsedSearchTerms into addConditionParam() calls on a Listing.
```

Namespace mirrors directory 1:1: `PimBay\SearchQuery\Pimcore\...` → `src/...`.

## Public library mode

Always applies — every repo here is published on Packagist. Every exported-symbol change is a public API decision.

- **Always ask before**: new `composer.json` dep, changing a public signature, new architectural pattern, touching >1 package at once.
- **Never without instruction**: delete a public class/file, rename an exported symbol, break wire/schema compatibility, add a build-affecting dev dependency.
- Two valid approaches → present both, no silent pick.
- Multi-file change → list files, confirm scope, then proceed.

## Testing

- **PHPUnit 11.5+**, `tests/Unit/` only — no `tests/Functional/` suite. Spinning up a real `AbstractListing` needs a bootstrapped Pimcore Kernel plus a MySQL/MariaDB connection, unlike Doctrine's SQLite-backed functional tests; that's out of scope for CI here.
- Every collaborator faked, by a hand-written fixture rather than a PHPUnit double: `tests/Fixture/SpyListing.php` extends the real `DataObject\Listing` and declares the DB-facing methods itself. `createMock()` cannot configure `load()`/`loadIdList()`/`getTotalCount()`/`getCount()` — Pimcore declares them only as `@method` annotations, so PHPUnit throws `MethodCannotBeConfiguredException`.
- **Coverage: 100%** — hard gate; a dropped coverage change comes with new tests, not an exclusion.
- **Mutation testing: Infection, min MSI 100%** (`composer test:mutation`) — an escaped mutant needs a stronger assertion, not a suppressed mutator.
- **`#[Test]` attribute**, not `test`-prefix. `#[DataProvider('methodName')]` for parameterized cases.

## Guardrails

- No new `composer.json` deps without proposing them explicitly.
- Targeted diffs — don't rewrite a file for a small fix.
- No unrequested docs/test scaffolding.
- Don't introduce a DI container, config loader, or logging framework — flag the need, don't silently add.
- Domain-vocabulary vs local-shape placement unclear → ask, don't guess.
- New failure case → check for an existing exception (named constructor) before adding one.
