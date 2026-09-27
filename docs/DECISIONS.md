# Decisions

> Append-only log of decisions specific to _this_ project.
> Never edit or delete a past entry — if a decision changes, add a new entry that supersedes it and says so.
>
> **What belongs here** (test): would changing this silently break correctness, compatibility, or behavior if someone didn't know why it was done this way?
> If yes → here.
> If it's a cheap/local implementation detail → docs/context.md instead.
> If it's a pattern repeated across multiple repos → AGENTS.md instead, not here.

## `count()`/`pageView()` use `getTotalCount()`, never `getCount()`

**Date:** 2026-09-18

**Decision:** `PimcoreListingAdapter::count()` and `pageView()` always call `getTotalCount()`. `getCount()` is never used.

**Why:** `getCount()` returns `0` on some listing types once `setLimit()`/`setOffset()` have already been applied to the same listing instance — confirmed for `Asset\Listing`. See [pimcore/pimcore#5639](https://github.com/pimcore/pimcore/issues/5639). `getTotalCount()` doesn't have this problem and is the documented way to get a listing's unfiltered-by-pagination row count.

## Every `SearchTermsQuery` condition group is wrapped in explicit parentheses

**Date:** 2026-09-18

**Decision:** `SearchTermsQuery::apply()` always wraps each OR/AND group in `(...)` before passing it to `addConditionParam()` — never a bare, unparenthesized fragment.

**Why:** `addConditionParam()` does not parenthesize the fragment it's given — see [pimcore/pimcore#1838](https://github.com/pimcore/pimcore/issues/1838).

## `PimcoreListingAdapter` takes a union of Pimcore's six concrete listing classes

**Date:** 2026-09-27

**Decision:** The constructor parameter (and `cloneListing()`'s return type) is `Asset\Listing|DataObject\Listing|Document\Listing|Element\Note\Listing|Element\Tag\Listing|Version\Listing`.

**Why:** `load()`, `loadIdList()` and `getTotalCount()` are **not declared on `Pimcore\Model\Listing\AbstractListing`**. They exist only as `@method` annotations on the concrete listing classes and are resolved at runtime by `AbstractModel::__call()`, which forwards to the DAO. Typing against `AbstractListing` therefore produced `method.notFound` errors under PHPStan level max, and made the class untestable.

Those exact six classes are the complete set in `pimcore/pimcore` that declare all three methods — verified on the 11.x, 12.x and 2026.2 branches. A narrower union would exclude `Note`/`Tag`/`Version` listings, which satisfy the contract just as well.

**Consequence:** a custom `AbstractListing` subclass that does not extend one of the six is no longer accepted. That is the honest contract — such a subclass would have had to declare the three methods itself anyway.

**Alternatives considered:** Keeping `AbstractListing` and routing through `$listing->getDao()`, which *does* declare `load(): array` and `getTotalCount(): int`. Rejected — `loadIdList()` is not on the DAO, so `ids()` would still be unresolvable and the class would need two different access paths.

## Pimcore's two silent-result behaviours are documented, not worked around

**Date:** 2026-09-28

**Decision:** Two upstream behaviours are recorded in `docs/context.md` under Known limitations and left alone in code.

`Note`/`Tag`/`Version` wrap their `getTotalCount()` in a `try`/`catch` that returns `0` — every other listing class lets the error out. A malformed condition on one of them therefore reads as "no results" through a bare `count()`. Those same DAOs' `load()` has no catch, so every other adapter method surfaces it; `pageView()` reads `load()` before `count()`, which is what keeps the `PageAssembler` path honest.

`DataObject`/`Asset`/`Document` load in two phases — a query for IDs, then `getById()` per ID — and skip any row whose object does not hydrate (`getById()` returns `null` on its `NotFoundException` path or on a `typeMatch()` miss; Asset and Document additionally skip an empty `type` column). So `pageView()->totalCount` counts database rows while `results` holds hydrated objects, and the two can disagree; and `pageSlice()` derives `hasMore` from `\count($results) > $size`, which reads `false` if the dropped row happened to be the lookahead one.

**Why nothing is worked around:** both would need a second query to detect, which is precisely what `SliceAdapter` exists to avoid — its contract says `hasMore` must come from fetching `size + 1` rows and never from a separate count. Asking for extra rows "to be safe" guarantees nothing either.

The `hasMore` imprecision is also the least costly of the two to accept here. Pimcore gives an exact count cheaply, so `PageAdapter` is the natural family over a listing and `pageView()` is what consumers will reach for; `SliceAdapter` earns its keep against sources where counting is expensive — Elasticsearch and the like — not against a SQL listing that can count deterministically.

**Careful:** do not "fix" `pageSlice()` by counting. That trades a rare, documented imprecision for a guaranteed contract violation on every call.

## `SearchTermsQuery` rejects a duplicate condition group and offers `$tag` to disambiguate

**Date:** 2026-09-27

**Decision:** `apply()`/`applyString()` take an optional `?string $tag`. Before adding a group, a throwaway `clone` of the listing is probed; if the clone's condition count does not grow, `DuplicateConditionException` is thrown. A non-null `$tag` is appended to the fragment as an inert SQL comment (`(name = ?) /* q1 */`) and is whitelisted against `/^[\w.-]+$/`, or `InvalidConditionTagException` is thrown.

**Why:** `AbstractListing::addConditionParam()` stores into `$this->conditionParams[$condition]` — **keyed by the SQL fragment string**. `SearchTermsQuery`'s fragments depend only on the column and the number of terms, never on the values, so two searches on the same column produce the same key and the second silently replaces the first. Reproduced against Pimcore 11.5: applying `dog` then `cat` to `name` leaves a single condition bound to `cat`, with no error. The Doctrine sibling cannot hit this because its `$paramPrefix` makes every fragment unique; dropping that parameter here is what opened the hole, and `$tag` closes it.

The probe uses a clone, and counts rather than predicting the key Pimcore builds, for two reasons: on a collision the caller's listing is left untouched instead of half-mutated, and nothing depends on Pimcore's internal `'('.$condition.')'` wrapping, which is free to change.

`$tag` lands in raw SQL, so it is whitelisted rather than escaped. The charset excludes `*` and `/`, which makes closing the comment early impossible, and `!`, which rules out a MySQL executable comment (`/*! ... */`).

**Alternatives considered:** Validating nothing and documenting "one `apply()` per column per listing". Rejected — silent data loss is the worst possible failure mode for a search filter, and a document does not prevent it. Detecting the collision after the fact instead of on a clone was also rejected: it is one line shorter but leaves the listing holding the replacement.

## `tests/Unit/` uses a hand-written `SpyListing` fixture, not PHPUnit mocks

**Date:** 2026-09-27

**Decision:** All tests live in `tests/Unit/`; there is no `tests/Functional/` suite. `tests/Fixture/SpyListing.php` extends the real `DataObject\Listing`, declares the DB-facing methods itself and records mutator calls into a shared `CallLog`.

**Why no functional suite:** `search-query-doctrine`'s works because a Doctrine `QueryBuilder` runs standalone against an in-memory SQLite connection. `AbstractListing` has no equivalent — a real listing needs a running Pimcore `Kernel` and a MySQL/MariaDB connection with class definitions loaded, which is a lot of CI to carry for an adapter that calls six well-known public methods.

**Why a fixture and not a mock:** `createMock()` cannot configure `load()`/`loadIdList()`/`getTotalCount()`/`getCount()` — Pimcore declares them only as `@method` annotations, so PHPUnit throws `MethodCannotBeConfiguredException`. Writing the fixture was only safe once the real class had been inspected, against `pimcore/pimcore` v11.5.14.1.

The fixture also buys assertions a mock cannot make: `getCount()` throws instead of returning a value, so the adapter reaching for it fails the run, and `CallLog` is shared through `clone`, so a test can observe what the adapter did to a copy it never handed back while asserting the original stayed untouched.

## The `pimcore/pimcore` floor is `^11.5`, and the 11.x docker combo ignores that line's advisories

**Date:** 2026-09-28

**Decision:** `composer.json` requires `pimcore/pimcore: ^11.5 || ^12.0 || ^2026.0`. The `php83-pimcore11` docker combo — and only that combo — writes `policy.advisories.ignore: ["pimcore/pimcore"]` into the image's own copy of `composer.json` before resolving.

**Why `^11.5` and not `^11.0`:** 11.0 and 11.1 declare `php: ~8.1.0 || ~8.2.0`, and this package requires `php: >=8.3`, so `^11.0` accepted two minors Composer can never install next to it. 11.5 is also the line still carrying real projects, which is what the floor should describe.

**Why the combo ignores advisories:** Composer 2.10 refuses by default to install any release with a published advisory, and **every** 11.x on public Packagist has several — the earliest of them is fixed in 11.5.18, which is not published there. Without the ignore the combo cannot resolve at all, so 11.x would be supported on paper and never tested. The ignore is narrow on three axes: the 11.x combo only (a `case` on `PIMCORE_CONSTRAINT`), `pimcore/pimcore` only, so an advisory in any other dependency still stops the build, and the image's working copy only — `COPY . .` restores the repository's `composer.json` afterwards, so it exists in no committed file and reaches no consumer.

**Careful:** this is a statement about what the test matrix can install, not that those advisories are harmless. A consumer on 11.x meets the same block in their own project and has to decide for themselves.

The API is not what moved. `AbstractListing`'s `setLimit()`/`setOffset()`/`addConditionParam()`/`getConditionParams()` signatures, the six listing classes' three `@method` declarations and `AbstractModel::__clone()` nulling `$dao` are all present and identical in v11.0.0 — checked against that tag — so the floor is about installability, nothing else.

## `SqlHelper::escapeLike()` is a small local copy, not a shared dependency on `search-query-doctrine`

**Date:** 2026-09-18

**Decision:** `src/SqlHelper.php` duplicates `escapeLike()` (and only `escapeLike()`) rather than requiring `pimbay/search-query-doctrine` for it.

**Why:** LIKE-wildcard escaping is generic SQL logic, not Doctrine-specific, so depending on the Doctrine adapter package just for this one static method would make `search-query-pimcore` transitively pull in `doctrine/dbal` for a class that never touches DBAL. `likeEscapeClause()` (the explicit `ESCAPE '...'` clause) is intentionally not duplicated here — Pimcore only ever runs against MySQL/MariaDB, which already treats the copy's escape character as the implicit default `LIKE` escape character, so an explicit clause adds nothing. `search-query-doctrine` needs it because it also targets SQLite/SQL Server/Oracle, which don't define an implicit default.

**Alternatives considered:** Moving `SqlHelper` into `pimbay/search-query` (the core package) so both adapter packages depend on it without duplication. Deferred, not rejected — worth doing if a third SQL-backed adapter package appears and needs the same logic; not worth the migration for two copies of one function.

## `splitOnMarkers()` scans with a bounded `foreach`, not a hand-advanced cursor

**Date:** 2026-09-28

**Decision:** The scan iterates `str_split($value)` and skips consumed marker bytes with a `$skip` counter, compared as `0 !== $skip`. Supersedes the `while ($offset < $length)` loop with `++$offset` / `$offset += \strlen($marker)`. Ported verbatim from `search-query-doctrine`, where the same change was made first — the two implementations of this method are kept byte-identical.

**Why:** with a hand-advanced cursor, three separate mutators broke termination rather than the result — `Increment` (`++$offset` → `--$offset`), `PlusEqual` (`+=` → `-=`) and `Assignment` (`+=` → `=`). Each produced an endless loop that grew `$chunk` until the mutant process hit its memory cap, so Infection recorded a fatal error instead of a killed mutant. PHP's negative string offsets are what let it run away: `$value[-1]` is the last byte rather than a fault, so nothing stops the loop. `foreach` fixes the iteration count at the value's length, so a wrong `$skip` can only produce a wrong split — which an assertion kills. Mutation run here: 90/90 killed, no errors, against 85 killed + 3 errors before.

**Why not `preg_split()` either:** an alternation built from `preg_quote()` can never fail to compile, so the `false` arm `preg_split()` declares is unreachable — no test can pin it down and its fallback array is an unkillable mutant. This is why the scan is written out at all.

**Why `0 !== $skip` and not `$skip > 0`:** the counter is never negative, so the two are equivalent in every reachable state — but under `> 0` the initial `$skip = 0` and a mutated `$skip = -1` behave identically, which is an unkillable mutant. Comparing against zero makes the starting value observable.

**Careful:** do not "simplify" this back to a cursor, and do not widen the comparison to `>=`/`>`. Both reintroduce mutants the tests cannot kill by assertion. `aMultiCharacterMarkerMapsToOneWildcardAndTheRestOfTheValueSurvives()` and `aMarkerThatPrefixesAnotherNeverShadowsTheLongerOne()` in `SearchTermsQueryTest` are what keeps the `$skip` branch reachable at all — with single-character markers `$skip` is always `0` and every mutation of it escapes.
