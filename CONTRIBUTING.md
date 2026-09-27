# Contributing

Contributions are welcome — new adapter methods, additional Pimcore version compatibility, bug fixes, documentation.

## Public Domain Dedication

By submitting a pull request, you dedicate your contribution to the public domain under the same [Unlicense](LICENSE) terms as this project.
You assert that you have the right to make this dedication.

## Guidelines

- PHP 8.3+, `declare(strict_types=1)` on every file
- PHPStan level max, no errors, no baseline ignores
- 100% code coverage required
- 100% mutation score required (`composer test:mutation`, Infection — min MSI 100%, min covered MSI 100%); an escaped mutant means the test needs a stronger assertion, not a suppressed mutator
- `Adapter/PimcoreListingAdapter.php` covers every capability interface at once and reads the same on all six listing classes — a change that only holds for one of them belongs behind a check, not in the shared path
- The constructor's union of Pimcore's six concrete listing classes does not get widened back to `AbstractListing` — the methods the adapter calls are not declared there; see `docs/DECISIONS.md`
- `count()`/`pageView()` use `getTotalCount()`, never `getCount()`, and every condition group goes to `addConditionParam()` wrapped in explicit parentheses — both are correctness requirements recorded in `docs/DECISIONS.md`, not style
- New Pimcore version support means adding a combo to `docker-compose.yml`, `composer.json`'s `test:*` scripts, `.github/workflows/ci.yml`'s matrix and the README's compatibility table — not just widening the `composer.json` version constraint
