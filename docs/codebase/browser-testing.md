# Browser testing by owner

Browser specifications live with the owner of the rendered surface: `tests/Contexts/<Context>/<Capability>/Browser`, `tests/ReadModels/<Composition>/Browser`, `tests/Shared/ApplicationShell/Browser` or `tests/System/Acceptance/Browser`. This is one Playwright inventory, not overlapping projects or copied domain suites. See [Testing](testing.md) and [test navigation](../../tests/README.md).

The existing 17 specifications cover Alliance Access and Content, Kingdom Transfers, Alliance Assistant, Event Analysis, Event Management, Gift Codes, Progression, Recruitment Management, Roster, Screenshot Intake, Territory Planning, the application shell and cross-capability acceptance. The four Recruitment Management specifications remain complementary and grouped together.

## Commands and discovery

Run from a prepared repository root:

```sh
# Full browser/visual regression
npm run test:visual

# One rendered surface during development
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser
```

`playwright.config.ts` uses `testDir: './tests'` and `testMatch: '**/Browser/**/*.spec.ts'`. PHP tests, helper files and snapshots are not browser specs. Do not pass `--pass-with-no-tests` to conceal an empty or misspelled selection. A browser-only owner selection does not replace its PHP behavior or shared-dependency verification.

## Fixture seeding

Visual seeders live in their rendered surface's `Fixtures` folder, beside rather than inside `Browser`. Cross-capability setup belongs in `System/Acceptance/Fixtures`. CI and any spec-level setup must call the current fully qualified fixture class; do not retain old namespace aliases or duplicate seeders. See [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) for the ownership rule.

The stranded-fixture follow-up updates the ten existing CI class references and the Alliance Content spec's fixture class reference without changing their order, parameters, fixture bodies, assertions or images. It also preserves Event Command's explicit call into the cross-capability acceptance fixture. These are namespace changes, not a new seeding strategy or proof of runtime isolation. Seeders and browser tests have not executed during the hold.

## Snapshots and resources

The snapshot template is `{testDir}/{testFileDir}/__screenshots__/{testFileName}/{projectName}/{arg}{ext}`. The twelve existing ApplicationShell PNGs therefore live at `tests/Shared/ApplicationShell/Browser/__screenshots__/ApplicationShell.spec.ts/{desktop,mobile}/`. They were moved using their original Git blobs, not regenerated. All 17 spec files are byte-for-byte unchanged by the owner-first migration.

The desktop/mobile projects, assertions, setup hooks, timeout values, fingerprint checks, tolerances, retries and worker configuration are unchanged. Keep `workers: 1` and `fullyParallel: false`; owner folders do not isolate shared databases, ports or external identifiers automatically. Fixture commands still execute from the repository working directory.

File-based IDs and execution order can change after relocation. Runner discovery, title reconciliation, snapshot resolution and order/isolation verification are still required once the explicit test hold is lifted. Source/hash preservation is not a passing Playwright result. Visually review intentional changes before updating snapshots; never weaken assertions, relax tolerances or blindly regenerate baselines for a green run.
