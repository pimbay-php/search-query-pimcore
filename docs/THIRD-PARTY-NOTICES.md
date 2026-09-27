# Third-Party Notices

This project itself is released under [The Unlicense](../LICENSE).
It bundles or invokes the following third-party software, each under its own license.

## Composer dependencies

Full list with versions and licenses: run `composer licenses` — don't hand-maintain a duplicate of `composer.json`/`composer.lock` here.

Direct runtime dependencies:

| Package | License | Note |
|---|---|---|
| `pimbay/search-query` | Unlicense | — |
| `pimcore/pimcore` | Depends on the major — see below | Required directly; `composer licenses` reports whichever major you resolved. |

## `pimcore/pimcore` licensing differs per major

This package supports `^11.5 || ^12.0 || ^2026.0`, and the three majors are **not** licensed the same way.
Which one Composer resolves therefore decides the terms a consumer is bound by, so it is worth stating explicitly rather than leaving to `composer licenses`.

| Major | `composer.json` `license` | Terms |
|---|---|---|
| `11.x` | `GPL-3.0-or-later` | Dual: GPLv3 as Pimcore Community Edition, or a Pimcore Commercial License (PCL). Without a commercial agreement the default is GPLv3. |
| `12.x` | `proprietary` | Pimcore Open Core License (POCL). |
| `2026.x` | `proprietary` | Pimcore Open Core License (POCL). |

Verified against the `LICENSE.md` and `composer.json` shipped in `pimcore/pimcore` v11.5.14.1 and v12.3.12.1, and against the `2026.2` branch.

### What changed at `12.x`

Pimcore replaced the GPLv3/PCL dual licensing with the **Pimcore Open Core License (POCL)**, last updated June 2025.
POCL defines "Open Core Software" as software whose source is publicly available but which is **not licensed out as open source**.
Three points matter for anyone installing this package against Pimcore 12 or newer:

- Free production use is capped by a revenue threshold. An organization qualifies only if its total global annual revenue does not exceed **€5 million**, counted across parents, subsidiaries, affiliates and group companies. Eligibility is self-certified and subject to Pimcore's audit; exceeding the threshold requires a paid commercial license, and Pimcore reserves the right to charge fees retroactively from the date the threshold was crossed.
- Non-profit and educational organizations may qualify separately, subject to Pimcore's own criteria.
- POCL §3.4 forbids using GPLv3-licensed Pimcore software alongside POCL-licensed software, and forbids reverting from POCL back to GPLv3.

That last clause is why the change is not simply a relabelling: moving from `11.x` to `12.x` is a one-way step in licensing terms, not only in code.

## Notes

This is not legal advice, and the summary above is not a substitute for Pimcore's own license text.
This package's Unlicense terms cover only the code in this repository. They say nothing about Pimcore itself, and they cannot grant rights to it.
If the licensing terms decide whether you can use Pimcore at all, read `LICENSE.md` in the `pimcore/pimcore` version you actually resolved and take your own advice on it.
