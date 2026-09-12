# Test organization and performance plan

Status: In progress — PR #163 only — 2026-09-09

Working branch: `astra/codebase-hardening`. Do not merge, update `main`, create a replacement PR, or alter unrelated production behavior as part of this work.

This plan is part of the [codebase hardening program](../product/codebase-hardening-program.md) and supplements the [testing contract](testing.md). A plan, a successful file move, or a green narrow suite is not completion.

## Verified starting point

The source artifact for commit `f975432e742882f76353d422238511901fd7b12a` contains 283 PHP test files and 352 files under `tests`. PHPUnit currently discovers `tests/v3` through one suite named `Architecture V3`, even though it contains architecture, HTTP, persistence, concurrency, read-model and workflow tests. The distribution is 21 Architecture, 205 Contexts, 43 ReadModels, five Workflows, three Shared, two Acceptance, two Infrastructure and two Frontend PHP test files.

Fifty PHP test classes use `DatabaseMigrations`; 167 use `RefreshDatabase`. The test base already isolates cache prefixes before application boot. Preserve that isolation rather than replacing it with a shared cache namespace.

Main CI runs the full parallel PHP suite, while Architecture and Intelligence workflows independently run the complete serial suite. Capability-specific workflows also run selected contracts. These are observed duplication and setup-cost candidates, not proof of which individual tests are slow. The existing hardening ledger records full parallel execution around 24 minutes and serial timeouts; collect current timings before claiming an improvement.

## Required standard directory layout

Organize by test type first, then by the existing domain/capability name. Do not retain a version-number directory as the permanent test root.

```text
tests/
  Unit/                         # Pure PHPUnit; no Laravel boot, database or network.
    <Context>/<Capability>/
    Shared/
  Feature/                      # Laravel HTTP, console and user-facing contracts.
    <Context>/<Capability>/
    ReadModels/<Workspace>/
    Workflows/<Workflow>/
    Acceptance/
  Integration/                  # Real database, adapter and transaction contracts.
    <Context>/<Capability>/
    Infrastructure/
    Concurrency/                # Real competing connections and lock semantics.
      <Context>/<Capability>/
  Architecture/                 # Source ownership, imports, namespaces and boundaries.
  Frontend/                     # Frontend source/contract tests, not browser execution.
  Browser/                      # Playwright specifications and reviewed snapshots.
  Fixtures/                     # Shared inert evidence and fixture data.
  Support/                      # Scenario factories, assertions and test utilities.
  TestCase.php                  # Laravel base with existing isolation behavior.
```

Directories are created when there are actual tests to place in them, not as empty placeholders. PHPUnit suites must be disjoint, recursive within their declared roots, and exclude support/fixture files from test discovery. Browser tests keep an independent Playwright entry point.

### Classification and migration rules

Read the actual dependencies and assertions, not only the filename. A class importing the Laravel base is not a unit test. A test of HTTP authorization remains a feature test even when it uses PostgreSQL. A test asserting committed cross-connection visibility, lock ordering or database constraints belongs in Integration; preserve the real mechanism being tested. Split mixed classes only when behavior and data-provider inventories can be reconciled exactly.

Move source-structure checks to Architecture and pure logic tests to Unit. Classify existing Contexts, Shared, Infrastructure, ReadModels and Workflows tests individually. Move acceptance HTTP matrices under Feature/Acceptance and browser specifications under Browser. Consolidate `tests/Fixtures` and `tests/v3/Fixtures` without overwriting same-named data or changing fixture contents.

Update every namespace, import, data-provider reference, `__DIR__`-relative lookup, source scanner, fixture path, PHPUnit suite, Composer command, PHPStan/Pint input, Playwright path, TypeScript configuration, frontend quality script, workflow path filter, workflow command and current documentation reference affected by a move. Do not leave aliases, duplicate files or old-suite fallbacks that conceal incomplete migration. Preserve class names unless renaming is separately justified; folder cleanup alone does not require changing a behavioral contract's name.

## Execution phases and acceptance evidence

### 1. Inventory and reproducible baseline

- [x] Resolve PR #163's actual branch and inspect its source through the GitHub connector.
- [x] Retrieve the exact source/dependency/runtime artifact and confirm its recorded SHA.
- [x] Count existing test files and identify current suite roots, database-reset traits and duplicate complete-suite invocations.
- [ ] Record discovered PHPUnit test identities, including data-provider cases, plus Playwright test identities before moving files.
- [ ] Capture current wall-clock time, test/assertion totals, slow classes, setup cost, process count, PostgreSQL version and runtime versions. Separate dependency installation from test execution.
- [ ] Record pre-existing failures independently from reorganization regressions.

### 2. Standardize the physical test layout

- [ ] Produce a deterministic old-path to new-path manifest and review classification.
- [ ] Move tests, support and fixtures; rewrite namespaces and all current references atomically.
- [ ] Configure named Unit, Feature, Integration, Architecture and Frontend suites without overlap or omissions.
- [ ] Add a layout/discovery guard for unclassified PHP test files, duplicate destinations, stale executable paths and namespace mismatches.
- [ ] Prove that the normalized discovered test inventory is unchanged, including data-provider cases; separately explain any intentionally added tests.

### 3. Remove measured setup waste without weakening isolation

- [ ] Profile repeated schema rebuilds before changing the reset strategy.
- [ ] Keep transaction rollback for tests whose semantics allow it. Evaluate schema reuse plus data truncation for committed-state/concurrency tests; never wrap those tests in an outer transaction that hides rows from competing connections or changes after-commit behavior.
- [ ] Keep explicit fresh-migration/schema tests on an actual clean database. Identify tests that alter schema before allowing them to reuse a migrated schema.
- [ ] Reset data, sequences, application state, environment, cache, queue fakes, time and extra database connections where applicable. Verify both successful and exceptional teardown paths.
- [ ] Keep database/cache namespaces isolated per parallel worker. Preserve real PostgreSQL lock/constraint behavior, durability settings and bounded contention waits.
- [ ] Replace unnecessary repeated fixture work only after equivalent authority, scope, retry and failure assertions are demonstrated.

Do not delete assertions, skip tests, blanket-fake tested integrations, increase retries to hide flaky behavior, lower database durability, accept changed snapshots without review, or report a narrower test selection as a full-suite speedup.

### 4. Make local commands and CI predictable

- [ ] Provide explicit fast, unit, feature, integration, architecture, full, parallel and profiling commands with argument forwarding and nonzero failure exits.
- [ ] Keep full coverage authoritative for every PR. Consolidate redundant full-suite executions only when all existing test identities remain covered by a required complete run and capability-specific checks remain meaningful.
- [ ] Select safe parallelism explicitly and retain a serial route for tests or diagnostics that genuinely require it.
- [ ] Preserve fresh PostgreSQL installation, formatting, static analysis, frontend, visual, security and deployment/recovery gates. Update existing workflow paths and names rather than silently orphaning protected checks.
- [ ] Publish timings and diagnostics as artifacts even on failure, with immutable source SHA and exact commands. Avoid making profiler output a source of flaky timing thresholds.

### 5. Fix blockers and close out with evidence

- [ ] Resolve current visual-regression failures without indiscriminate snapshot refreshes.
- [ ] Run layout/discovery checks, strict PSR-4 validation, syntax, Pint, PHPStan, frontend scripts and browser discovery after migration.
- [ ] Run all PHP cases against PostgreSQL in the intended execution lanes; verify contention/commit-sensitive cases and repeat isolation-sensitive cases in differing orders.
- [ ] Run browser behavior and review any intentionally changed rendering.
- [ ] Compare equivalent before/after wall-clock measurements and report both test time and total pipeline time. State environment differences and uncertainty rather than inventing percentages.
- [ ] Update the testing guide, hardening program/ledger and PR description with actual completed work, remaining failures and immutable verification evidence.
- [ ] Confirm every published change is on PR #163's existing branch, with no force-push or merge.

## Completion rule

Complete means the physical directory migration, configuration/reference reconciliation, measured runtime optimization and applicable verification have all finished successfully on a containing commit. Open boxes remain open until their evidence exists. This test work does not close unrelated hardening findings or the repository-wide audit by implication.
