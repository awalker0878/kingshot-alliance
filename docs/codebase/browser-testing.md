# Browser testing by owner

Status: The complete normal browser suite passed at the verified revision recorded in the [runtime validation report](test-validation-2026-09-10.md). The execution hold has been lifted; PR #163 remains draft and unmerged because other work is incomplete.

Browser specs belong to their rendered surface: `tests/Contexts/<Context>/<Capability>/Browser`, `tests/ReadModels/<Composition>/Browser`, `tests/Shared/ApplicationShell/Browser` or `tests/System/Acceptance/Browser`. These form one Playwright inventory, not duplicate owner suites. See [Testing](testing.md), [test navigation](../../tests/README.md) and [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md).

## Commands and discovery

From a prepared repository root with the configured application and isolated visual database available:

```sh
# Complete browser/visual regression
npm run test:visual

# One rendered surface during development
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser

# Diagnose repeatability without retries
npm run test:visual -- tests/System/Acceptance/Browser/CapabilityAcceptanceMatrix.spec.ts --repeat-each=2 --retries=0

# Independent fast Node source contracts; no browser startup
npm run test:source-contracts
```

Playwright uses `testDir: './tests'` and `testMatch: '**/Browser/**/*.spec.ts'`. Node's owner-local `*.test.ts` contracts, PHP tests, helpers and snapshots are not Playwright cases. Runtime reconciliation preserves 62 title/project identities across 17 specs. The four RecruitmentManagement specs remain complementary. Never use pass-with-no-tests to conceal an empty or misspelled selection.

An owner browser selection does not replace its PHP, dependent-owner or complete application verification. Keep one worker and non-parallel files; owner folders do not automatically isolate shared databases, ports, profiles or external identifiers. Do not increase retries to conceal unreliable behavior.

## Fixtures and snapshots

Visual seeders belong in the rendered surface's Fixtures folder, beside Browser rather than inside it. Cross-capability setup belongs in System/Acceptance/Fixtures. Use current fully qualified fixture classes without old namespace aliases or duplicate seeders. Root support is reserved for genuinely cross-owner infrastructure. The fixture relocation preserved seeder bodies, ordering, parameters and identities while updating consumers explicitly.

The snapshot template is `{testDir}/{testFileDir}/__screenshots__/{testFileName}/{projectName}/{arg}{ext}`. All twelve existing ApplicationShell PNGs under `tests/Shared/ApplicationShell/Browser/__screenshots__/ApplicationShell.spec.ts/{desktop,mobile}/` are byte-identical to their pre-migration baselines. They were not regenerated to fix failures.

## Seven-surface acceptance

The matrix still visits all seven authenticated surfaces. Officer overview and Officer briefs are rendered in the dashboard, not the former nonexistent standalone URLs. Direct navigation now requires HTTP 200, clicked-link destinations are awaited and each surface is labelled as a test step.

Raw semantic assertions protect names, counts, power/progression changes, warning and freshness states, scoped relationships and resolved labels. A separately tested normalizer replaces only concrete fixture IDs and validated formatted timestamps. It does not mutate DOM nodes or erase surrounding behavioral text. Invalid dates fail, and timestamp-bearing surfaces must retain a valid rendered date. Full normalized text fingerprints remain independently checked for each surface and viewport.

The [acceptance review](acceptance-baseline-review-2026-09-10.md) records the fourteen inspected screenshots/text captures and fingerprint corrections. Raw and normalized text stay attached; per-surface screenshots are captured on a mismatch instead of for every passing surface. Existing configured failure screenshots remain available. This reduces diagnostic work on success without dropping assertions or relaxing tolerances.

Hosted focused run `34476453647` at `74d14b34` passed both viewports twice with retries disabled: four passes and no flaky, skipped or failing cases. Final normal Visual Regression run `34479708461` at PR head `e6f29ebbd40687c38a43554facb3ec9ef872ea8c` then passed all 62 cases with one worker in 5.6 minutes, as reported by Playwright. Its log lists each case once with no retry or flaky outcome. The actual checkout was `4485b87f0ab5aef0f93d2f70f8ae6b088697c22b`. This final result covers the complete browser inventory, not only the focused matrix.

These runs establish the recorded environment and fixture behavior, not all future schedules. The local host blocked Chromium navigation by policy, so browser verification used the approved hosted environment rather than bypassing that restriction. Review intentional rendered changes before modifying baselines; never delete scenarios, relax tolerances or regenerate snapshots blindly for a green result.


## Full readiness raster coverage

Long Transfer readiness pages use the owner-local capture helper instead of one oversized full-page texture. It captures the configured shell viewport and contiguous viewport-fitting main-content tiles below the measured sticky overlay. Coverage includes the initial rounded prefix, checks no gaps/overlap, asserts stable geometry and PNG dimensions, and rejects uniform-color output. Scrolling is instant only for screenshot positioning, without changing production CSS or viewport dimensions. Every tile is attached with its coverage/hash manifest so an expected digest cannot conceal missing lower content.

Generated receipt identities are normalized separately from their rendered labels and status. The normalizer replaces exactly one ULID and rejects missing or ambiguous identity text; it does not inject a new status or discard other content. Existing semantic, keyboard, filter, evidence, paging and overflow checks remain independent of the raster comparison. Current baseline review and containing verification are recorded under HARD-107/108 in the [delivery ledger](../product/codebase-hardening-delivery-ledger.md). Never replace review placeholders or expected fingerprints solely to obtain a green run.
