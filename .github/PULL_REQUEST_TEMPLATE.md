## What this does

<!-- One or two sentences. Link an issue if there is one. -->

## Checklist

CI already gates style/static-analysis/tests — no need to re-check those here.

- [ ] `docs/CHANGELOG.md` updated, if this changes public API behavior or anything else a consumer would need to know about
- [ ] `docs/context.md` updated, if this changes a convention, gotcha, or architectural rule an agent working in this repo would need to know
- [ ] `docs/DECISIONS.md` updated, if this changes or reverses a prior recorded decision
- [ ] New tests are in `tests/Unit/` — every collaborator faked (the listing through the `SpyListing` fixture, not a PHPUnit mock)
