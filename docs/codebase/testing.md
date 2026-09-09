# Testing

Status: Current — standard test layout — 2026-09-09

The test system is organized by **execution semantics first** and by domain ownership underneath those roots. A targeted or fast run is a development accelerator; it is never evidence that the complete application passes.

The authoritative PHP inventory is split into five disjoint PHPUnit suites in `phpunit.xml`. Browser tests remain a separate Playwright surface. `scripts/verify-test-layout.php` guards the suite topology, namespace/path alignment, legacy `tests/v3` references, accidental undiscovered PHP test files and reintroduction of per-test schema rebuilds outside explicit schema-lifecycle contracts.

## Observed toolchain

The repository requires PHP 8.5 and currently declares PHPUnit `^12.5.23` plus ParaTest `7.20.0`. The measured 2026-09-09 baseline used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0 and PostgreSQL 18.6. Browser verification uses Playwright 1.62.1 from `package.json`.

Those baseline versions and timings are evidence from the recorded baseline; they are not a claim that every developer machine or later CI image has identical patch versions.

## Suite structure

| Path / suite | Purpose | Resource expectations |
| --- | --- | --- |
| `tests/Unit` / `Unit` | Isolated PHP logic and inert contracts | Pure PHPUnit; no Laravel application bootstrap or database requirement |
| `tests/Feature` / `Feature` | HTTP, authorization, application behavior, persistence and read-model interactions | Laravel application; ordinary test isolation |
| `tests/Integration` / `Integration` | Committed-state persistence, lifecycle, after-commit, real infrastructure and transaction semantics | Real PostgreSQL semantics where required; do not replace with a different engine for speed |
| `tests/Integration/Concurrency` | Multi-connection locking, ordering and concurrency contracts | Real independent PostgreSQL connections; parallelize only after isolation is proven |
| `tests/Integration/Schema` | Explicit migration/schema lifecycle contracts, if introduced | The only ordinary test location where per-test schema rebuilds may be justified |
| `tests/Architecture` / `Architecture` | Source, reflection, route, dependency and boundary contracts | Primarily source/application-structure checks; should not rerun the full database-backed suite |
| `tests/Frontend` / `Frontend` | PHP-side frontend/source contracts that do not need a browser | No Playwright browser startup |
| `tests/Browser` | End-to-end and visual user journeys | Playwright/browser/runtime assets only when a browser is genuinely required |
| `tests/Fixtures`, `tests/Support`, `tests/TestCase.php` | Shared test infrastructure | Not independent PHPUnit suites |

The standard-layout migration records the exact old-to-new file mapping in `docs/codebase/test-layout-migration.json`. At migration time the 283 PHP test classes were assigned as 10 Unit, 194 Feature, 52 Integration, 24 Architecture and 3 Frontend classes. A later source-only pass moves four pure classes to Unit, recorded in [test-pure-bootstrap-migration.json](test-pure-bootstrap-migration.json), and adds one reference-reset regression class. The resulting 284 source files are 14 Unit, 190 Feature, 53 Integration, 24 Architecture and 3 Frontend; execution/discovery reconciliation remains pending.

## Database reset contracts

Choose database cleanup by the semantics the test must observe, not by whichever trait is fastest in isolation.

- Use transaction-based reset only when the behavior is valid inside the test transaction and no competing connection, committed-state visibility or after-commit behavior is part of the contract.
- Use Laravel `DatabaseTruncation` for schema-stable tests that need committed rows, independent connections, real locks, durable transaction boundaries or after-commit behavior. The schema is reused within the test process while data is cleared between cases.
- Reserve `DatabaseMigrations` for a genuine schema/migration lifecycle test under `tests/Integration/Schema/`. `scripts/verify-test-layout.php` rejects it elsewhere because rebuilding the complete schema for every method is both expensive and unnecessary for ordinary data isolation.
- Never substitute SQLite or another engine for PostgreSQL where PostgreSQL constraints, locking, transaction or concurrency behavior is under test.

The 2026-09-09 optimization converted all 50 classes that previously used `DatabaseMigrations` only for data cleanup to `DatabaseTruncation`. The 47-class deterministic batch is recorded in `docs/codebase/test-database-reset-migration.json`; the measured first hot spot and two HTTP Feature exceptions were reviewed and converted separately. This is a source-state fact, not a performance or passing-test claim until containing execution is completed.

### Migration-created reference rows

Committed-state tests using `DatabaseTruncation` must extend `Tests\TestCase`. Its shared setup restores migration-created plans, entitlements and event catalogue rows from `Tests\Support\MigrationReferenceData`; teardown restores the same baseline before the next case can start an ordinary rollback transaction. The reference tables are **not** excluded from truncation: their mutations and inserted test rows must be discarded too.

The snapshot is process-local and keyed by the PostgreSQL connection target, including the actual worker database. It is established only after a fresh migration, not from a database with unknown fixture history. The shared setup may perform one additional fresh migration when an earlier transactional test has set Laravel's migrated flag without creating this snapshot. Database fixtures belong in the case's `setUp()` after `parent::setUp()`; all current committed-state classes use only the framework database-reset trait. Do not add fixture-mutating helper-trait setup ahead of baseline capture/restoration.

A future migration that populates an additional table requires explicit review of the reference-table order and identity sequence handling. Snapshot capture rejects an unclassified populated table instead of silently erasing it on later resets. Do not work around that failure by exempting a mutable table, disabling a constraint or capturing test data as the new baseline.

The initial repair and its six regression cases are source-checked only; execution remains required, including mixed `DatabaseTruncation`/`RefreshDatabase` order and the full two-worker suite.

## Development commands

Use the smallest trustworthy scope while editing:

```sh
# Fast feedback: isolated + architecture + non-browser frontend contracts
composer test:fast

# Individual PHP suites
composer test:unit
composer test:feature
composer test:integration
composer test:architecture
composer test:frontend-contracts

# One file or a known domain subtree
vendor/bin/phpunit tests/Feature/path/to/SpecificTest.php
vendor/bin/phpunit tests/Feature/Contexts/<Domain>

# Complete PHP regression
composer test

# Complete PHP regression using the repository's existing parallel runner
composer test:parallel

# Browser / visual verification
npm run test:visual
```

Named suite commands use PHPUnit's empty-suite failure mode so an unexpectedly empty selection does not silently report success. A domain can span more than one execution root. When a change touches both pure logic and persistence, run the relevant `Unit`, `Feature` and/or `Integration` paths rather than assuming one directory represents the complete domain.

There is currently no dedicated repository coverage command. Do not add coverage instrumentation to ordinary development runs; when coverage verification is required, use the explicitly configured coverage environment/CI path rather than silently changing the normal test command.

## Staged validation workflow

1. **During editing:** run the directly relevant test file(s), domain path(s), or `composer test:fast` when the change affects shared pure/source contracts.
2. **After a coherent change:** run the affected domain across every relevant suite plus shared dependencies it relies on.
3. **Before declaring completion:** run the repository-required complete verification, including full PHP regression and browser/security/acceptance gates that apply to the change.

Do not rerun the entire suite after every small edit. Conversely, do not describe a targeted run as application-wide verification.

## Changed-code selection

Automated changed-code selection is **not yet authoritative**. Until a conservative dependency-aware selector is implemented and its zero-selection, fallback and shared-infrastructure behavior are verified, select affected tests explicitly.

Always broaden validation when changing shared infrastructure such as:

- `tests/TestCase.php`, `tests/Support` or common fixtures;
- application providers, middleware, authentication or authorization infrastructure;
- migrations, database configuration, transaction helpers or shared persistence code;
- Composer/npm dependencies or test-runner configuration;
- `phpunit.xml`, Playwright configuration, CI workflows or test-selection scripts.

A future selector must print what it selected and why, include test-file changes, understand shared dependencies, fail safely when selection is unexpectedly empty, and fall back to broader verification when mapping is uncertain.

## Parallelism and isolation

The repository already supports ParaTest through `composer test:parallel`, but worker count must be measured rather than maximized automatically. Before increasing parallelism, verify isolation for databases, caches, queues, sessions, temporary files, storage paths, ports, browser profiles and global state.

Keep transaction/concurrency tests effectively isolated where concurrent execution would alter the behavior being verified. Production-equivalent PostgreSQL constraints, locks, transactions, authentication and security boundaries must not be weakened to improve timing.

The recorded baseline used two workers with PostgreSQL durability settings enabled. CI comparison runs are explicitly pinned to two workers through `composer test:ci`; the ordinary developer `composer test:parallel` command still uses the local runner default. Use comparable worker counts and environment settings for before/after performance claims, and report wall-clock time separately from aggregate worker/test time.

Playwright remains `workers: 1` with `fullyParallel: false`. Do not raise it until database state, ports, browser profiles, fixtures and global visual state have been shown to be isolated under repeated execution.

## Profiling and performance baseline

The existing reproducible PHP baseline command was:

```sh
php artisan test --parallel --processes=2 --log-junit /path/to/baseline-junit.xml
```

At the recorded source revision it completed 1,482 tests / 82,984 assertions in 9:35.23 wall time. Aggregate JUnit test duration was 1,131.400 seconds. The architecture-only lane separately completed 63 tests in approximately 1.53 seconds, demonstrating the cost of making a narrow architecture gate rerun unrelated database-backed behavior.

Main CI now writes `storage/logs/phpunit-junit.xml` from its two-worker full regression and retains it as the `phpunit-results` artifact on success or failure when the test phase is reached. Use that artifact to compare class/case aggregate duration against the baseline; do not infer a speedup from configuration changes alone.

Until an equivalent post-optimization run is recorded, treat the baseline as the comparison point rather than inventing a new performance budget. When profiling, retain JUnit/timing artifacts, identify slow classes/files and separate setup cost from actual test execution where possible. Record revision, environment, worker count and cold/warm conditions with every timing claim.

## CI dependency and specialized-gate policy

PHP workflows may reuse Composer's **download archive cache** keyed by `composer.lock`, but they must still execute a normal locked `composer install`. Do not cache `vendor/`, generated application configuration, database state or test outputs as a substitute for installation/isolation.

The mandatory main CI remains authoritative for full PHP, full frontend, fresh PostgreSQL installation, security and downstream container/staging/recovery coverage. Specialized workflows should add earlier domain-specific signal or unique contracts, not repeat an entire main-CI lane. Exact subset frontend lanes for Gift Code and Intelligence were removed for this reason; King Perks' targeted build and KingdomMaps' geometry/source checks remain because they exercise distinct configurations/contracts.

## Architecture and behavior verification

Architecture tests must continue deriving rules from the architecture rather than maintaining a second hardcoded capability registry. Important boundaries include context ownership, cross-context imports, write/read separation, HTTP adapter responsibilities, transaction ownership, authorization and persistence rules.

Behavior verification must continue protecting identity, authority, scope, transactions, concurrency, retry/idempotency and business invariants. Moving a test between suites or changing its cleanup strategy changes execution ownership, not the behavior it is expected to protect.

## Visual regression baselines

Playwright visual baselines may be refreshed only when the rendered change is intentional and has been visually reviewed. Do not delete cases, relax tolerances or blindly regenerate snapshots to obtain a green run. Dynamic values may be normalized only where the semantic assertions still verify the underlying behavior.

## Fresh-install and full certification

The primary backend verification must continue proving a clean PostgreSQL installation with the repository migrations. Main CI initializes PostgreSQL with the required parallel lock capacity while verifying `fsync`, `synchronous_commit` and `full_page_writes` remain enabled before the fresh-install check. A standard folder layout or faster cleanup strategy does not replace fresh-install, security, acceptance, static-analysis or browser verification.

Final architecture certification must inspect more than tests named `Architecture*`:

```text
directories
namespaces
imports
Eloquent relationships
database ownership
controllers
routes
actions
permissions
transactions
events
listeners
tests
documentation
CI
```

Any change to a context boundary, cross-context contract, route ownership or persistence rule must update the relevant tests and documentation in the same pull request.
