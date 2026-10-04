# Context

> Working memory, not a historical record.
> Continuously edited, not append-only — unlike DECISIONS.md.
> When something here resolves: delete it if it was only ever local/temporary, or promote it to DECISIONS.md if it turned out to matter beyond this moment.
> Don't let resolved items pile up here.

## Current focus

Nothing in flight.

## Open questions

- None.

## Known limitations / non-goals (for now)

- No `CursorAdapter` implementation — same reasoning as `search-query-doctrine`.
- `getTotalCount()` on `Note`/`Tag`/`Version` listings catches the exception and returns `0`, so a bad `$column` on one of them reads as an empty result rather than an error. Only a bare `count()` is silent: those DAOs' `load()` has no catch, so `pageView()`/`all()`/`head()`/`pageSlice()` surface it normally.
- `load()` on `DataObject`/`Asset`/`Document` silently drops a row whose object will not hydrate — `getById()` returning `null`, plus an empty `type` column on Asset/Document. `pageView()->totalCount` counts DB rows while the results are hydrated objects, so the two can disagree, and `pageSlice()`'s `hasMore` can read `false` when the dropped row was the lookahead one. Accepted, see `docs/DECISIONS.md`.

## Implementation notes

- `cloneListing()` is safe because `AbstractModel::__clone()` nulls `$dao` and `setLimit()`/`setOffset()` call `setData(null)`, so a clone re-queries instead of returning cached rows. Verified against `pimcore/pimcore` v11.5.14.1.
- It then copies the DAO's `onCreateQueryBuilder()` hook onto the clone by reflection: the private `queryBuilderProcessors` list on Pimcore 12.2+, `onCreateQueryBuilderCallback` before. Chosen by property presence, not version, so dev branches and replaced packages work.

## Ideas / future plans

- Move `SqlHelper` into `pimbay/search-query` (core) if a third SQL-backed adapter package ends up needing the same LIKE-escaping logic — see `docs/DECISIONS.md`.
