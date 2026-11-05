# PR 3 — catalogue consolidation (draft)

**Parent baseline:** verified merged commit `a5151e717020cfe00a32aeddcca6279f9c7042fe`. Its post-merge workflow [41506300260](https://github.com/awalker0878/kingshot-alliance/actions/runs/41506300260) verified 15 fingerprints.

## Initial implementation

Move Transfer Evidence English messages into the canonical `messages/transfers/en.ts` domain. Remove the English-only lazy overlay from `loader.ts` and delete its unused file. Other 16 language catalogues already contain their complete extension messages, and remain unchanged. The consolidated object spreads **the original base keys first and overlay keys second**, exactly preserving the historical overlay-wins semantics (including any duplicate keys).

This is one migration slice, **not complete overlay consolidation**. Remaining overlays and cross-domain duplicate ownership need a subsequent patch, and the PR must stay draft.

## Byte-for-byte release gate

`scripts/check-localization-pr3-parity.mjs` requires the exact committed PR 1 corrected hashes for **effective-all-locales**, **effective-en**, both server catalogues, PHP hashes, and semantic risk inventory. The PR3-specific CI runs a real Vite build, the existing localization chunk and performance-budget checks, then exports the **current** effective runtime for every domain and all 17 locales. Raw English sources are intentionally allowed to change because this is structural consolidation; **effective runtime values may not change at all**.

Do not assert zero diff until the PR3 CI run prints the passing hashes. Review the generated `vite-chunks.json` for regressions; chunk names may change due to deleted overlay modules. Preserve page and localization chunk budgets.

## Remaining scope before review

- [ ] Verify six parity hashes and existing full frontend/backend/visual checks on this branch
- [ ] Trace each overlay layer and document the winning module, including intermediate replacements
- [ ] Consolidate remaining English-only overlays incrementally, with parity after every slice
- [ ] Review duplicate keys and domain ownership before deleting or moving anything
- [ ] Capture before/after individual page chunk sizes without imposing arbitrary +1KiB allowances
- [ ] Keep PR 4 independent; no locale-cookie, browser preference or account persistence changes
