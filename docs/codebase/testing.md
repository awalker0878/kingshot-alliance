# Testing

Status: Current — owner-organized execution tiers — 2026-09-09

Tests are organized by **execution semantics first**, then by the owner of the behavior. A targeted run is a development accelerator, never proof that the whole application passes. [Test navigation and naming](../../tests/README.md) explains where to place, rename, consolidate or split a test.

## Toolchain and verification status

The repository requires PHP 8.5. The inspected dependency artifact contains Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0 and Pint 1.30.4; the browser package is Playwright 1.62.1. Inspect installed versions before choosing commands; a lockfile or historical artifact is not evidence of a developer machine's runtime version.

The current continuation starts at `9df8a99a3e07b48e1abdddfbcf8711b911320e8b`. `deddea08` splits mixed pagination checks by resource requirements. `c721daa` separates Transfer source checks from guard behavior and consolidates both Evidence registration methods under the Evidence owner. Across three original PHP classes, all eight original test methods remain exactly once in five focused classes; helpers and assertions are preserved. Only the two extracted source methods substitute `RepositoryPath::fromRoot()` for `base_path()`. The plain PageSlice method and two source methods no longer require Laravel startup; no elapsed saving is claimed.

`3524c496` groups all 17 browser specifications under their Context, ReadModel, Shared or Acceptance owner. It reuses every spec's original Git blob and the complete 12-PNG ApplicationShell baseline tree, moved to match the unchanged snapshot template. Browser titles, assertions, setup hooks, manual fingerprints and timeout values are unchanged. File-based IDs and execution order may change; runtime inventory and isolation reconciliation remain required. See [Browser navigation](browser-testing.md).

The source inventory accounts for **287 PHP test files**: 16 Unit, 189 Feature, 53 Integration, 26 Architecture and 3 Frontend. This is the prior 285-file inventory plus the two net class splits, not new scenarios or runtime discovery. No runner configuration, test-selection filter, production behavior, database reset implementation, retry count, coverage threshold or worker count changed in this continuation.

Source-method comparisons, browser blob/snapshot reconciliation and host PHP 8.4.23 syntax checks were performed. The five resulting test files and updated source-certification script match the prepared Git blob hashes. The script and two operational/product references change only their browser paths. Syntax checks do not execute the tests or the source-certification script. PHP 8.5 target-runtime validation, formatting/static analysis, PHPUnit discovery/execution, browser execution, order/isolation checks and after-change timing remain pending for the final revision. No tests, migrations, benchmarks or CI dispatch were run. Commits carry `[skip ci]` during the explicit execution hold; that does not satisfy or permanently disable the required gates.

## Suite structure and ownership

`phpunit.xml` defines five disjoint recursive roots. Every PHP test belongs to exactly one; domain folders are not extra overlapping suites.

| Suite | What belongs here | Resources |
| --- | --- | --- |
| Unit | Isolated logic and inert value/interface contracts | Pure PHPUnit; no application bootstrap or database |
| Feature | HTTP, authorization, application behavior, persistence and composed reads | Laravel application and appropriate ordinary isolation |
| Integration | Committed-state, after-commit, infrastructure and transaction behavior | Real PostgreSQL/independent connections where semantics require them |
| Architecture | Ownership, dependencies, source boundaries, reflection and actual application registration | Pure PHPUnit for source/reflection; Laravel for real wiring/routes/scheduler |
| Frontend | PHP-side frontend source contracts | No browser startup; not a substitute for browser journeys |

`tests/Browser` contains Playwright journeys grouped by rendered-surface ownership. `tests/Fixtures`, `tests/Support` and `tests/TestCase.php` are support, not execution suites.

Use `Contexts/<Context>/<Capability>`, `ReadModels/<Composition>`, `Workflows/<Workflow>` and `Shared/Infrastructure/<Concern>` below the appropriate tier. The read-model boundary classes live under [Architecture/ReadModels](../../tests/Architecture/ReadModels/README.md); capability boundaries live under [Architecture/Contexts](../../tests/Architecture/Contexts/README.md). Repository-wide rules and cross-application acceptance matrices remain explicitly cross-cutting instead of being assigned to an arbitrary context.

Frontend contracts are grouped under GameWorld/Players, Alliance/Content and GameWorld/KingdomTransfers. Cross-route throttle behavior is under `Feature/Shared/Infrastructure/Security`; cache namespace and migration-reference harness checks are under `Integration/Shared/Testing`. The separate top-level Feature/Infrastructure and Integration/Infrastructure buckets are gone.

The existing `Integration/Concurrency/Contexts` and `Integration/Concurrency/Workflows` paths remain part of the Integration inventory. Include them in domain selections. `Integration/Schema` is reserved for genuine schema-lifecycle contracts if introduced. Neither label is permission to change engines, mocks, isolation or scheduling.

### Separate source contracts from real wiring

`Tests\Support\RepositoryPath::fromRoot()` locates source files independently of test nesting and does not cache their contents. Source-only classes use `PHPUnit\Framework\TestCase`. Real container, route, middleware, scheduler, encryption and persistence contracts keep `Tests\TestCase`.

Within `Architecture/Contexts/Intelligence/Evidence`, `EvidenceReferenceContractTest` owns family-neutral interface reflection. `EvidenceReferenceBindingTest` consolidates the unchanged general and progression registration methods, both using the real container. Under the Transfer owner, `Architecture/Contexts/GameWorld/KingdomTransfers/TransferEvidenceWriteBoundaryTest` inspects guard usage and provenance fingerprints; `Feature/Contexts/GameWorld/KingdomTransfers/TransferEvidenceReferenceGuardTest` retains same-alliance/approval behavior with actual Laravel validation. These complementary contracts replace the former mixed class without removing its scenarios.

Pagination uses matching `Shared/Infrastructure/Pagination` owner folders across Unit and Feature. `PageSliceTest` verifies the plain response shape without a framework. `ScopedCursorCodecTest` verifies opaque encrypted cursors and cross-scope rejection through the actual application. Do not replace that encryption/validation path with mocks simply to make it as cheap as the data-object check.

`scripts/verify-test-layout.php` guards suite topology, namespace/file alignment, duplicate declarations, undiscovered PHP test files, stale versioned root references and ordinary per-test schema rebuilds. Source guards do not replace runtime discovery or behavioral verification.

## Exact development commands

Run commands from the repository root in a prepared PHP 8.5 environment. During an explicit no-test hold, these are documented commands, not executed checks.

```sh
# Fast feedback: Unit + Architecture + non-browser Frontend
composer test:fast

# One execution tier
composer test:unit
composer test:feature
composer test:integration
composer test:architecture
composer test:frontend-contracts

# One owner across complementary tiers
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Architecture/ReadModels/Progression \
  tests/Feature/ReadModels/Progression

# Pagination data and real encrypted-cursor behavior
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Unit/Shared/Infrastructure/Pagination \
  tests/Feature/Shared/Infrastructure/Pagination

# Real Evidence registration contracts
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Architecture/Contexts/Intelligence/Evidence/EvidenceReferenceBindingTest.php

# Full PHP regression: serial, existing local parallel, or fixed two-worker CI
composer test
composer test:parallel
composer test:ci

# One browser surface for development only
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser

# Full browser/visual verification
npm run test:visual
```

Suite commands fail on an unexpectedly empty suite. Verify that explicitly selected directories exist and include all relevant owner tiers; do not assume one folder represents every domain dependency. There is no authoritative changed-code selector yet. Do not treat a filename match, a zero selection or a narrow passing run as full verification.

There is no dedicated repository coverage command. Keep ordinary feedback runs free of unnecessary coverage instrumentation. When coverage is required, use the explicitly configured coverage environment and preserve required thresholds.

## Staged validation and selection fallback

While editing, run directly relevant tests. After a coherent change, include the affected owner across its relevant tiers and shared dependencies. Before completion, run the required complete PHP, frontend, browser, security, acceptance and deployment checks. Do not launch duplicate full suites after every edit or have contributors contend for the same resources.

Broaden to full verification for changes to base tests/support/fixtures, providers, authentication/authorization infrastructure, migrations, transaction helpers, shared persistence, dependencies, runner configuration, CI or selection logic. When mapping is uncertain, use broader verification. A future selector must include application and test changes, reason about dependencies, print its selected tests and reasons, fail safely on unexpected emptiness and have its fallback/failure behavior tested before adoption.

## Database reset and reference data

Choose reset strategy by the semantics being verified. Transaction-based reset is appropriate only when an outer test transaction does not invalidate committed visibility, independent connections, real locks or after-commit behavior. Schema-stable committed-state tests use Laravel `DatabaseTruncation`; intentional migration-lifecycle tests may use `DatabaseMigrations` only under `tests/Integration/Schema`. Never substitute another database engine or weaken constraints/durability/security to make a test faster.

Committed-state truncation tests must extend `Tests\TestCase`. The shared setup captures migration-created plans, entitlements and event catalogue rows through `Tests\Support\MigrationReferenceData` only after a fresh migration. It restores those actual rows after cleanup and at teardown so a following transactional test sees the proper baseline. Mutable reference tables are not exempted from truncation.

Snapshots are process-local and keyed by the actual PostgreSQL connection target, including the worker database. A transactional predecessor that set Laravel's migrated flag without creating a snapshot can require one additional fresh migration; never capture unknown fixture history as a baseline. Create case fixtures after `parent::setUp()`, not in a fixture-mutating trait hook ahead of capture/restoration.

A migration that populates another table requires review of reference-table insertion order and identity-sequence handling. Unclassified populated tables fail closed for review. Do not bypass that failure by excluding mutable tables, disabling constraints or recording test data as reference truth. Cleanup must preserve failing exit behavior, framework teardown and cache-environment restoration.

The earlier migration-reference repair and its six authored regression cases still require execution. Validate mixed `DatabaseTruncation`/`RefreshDatabase` ordering, schema reuse, committed fixture removal, reference mutations, identity handling and failure cleanup before accepting it. This continuation does not validate or modify that repair.

## Parallelism and isolation

The existing full CI path uses two PHP workers; the developer parallel command retains its local default. Do not increase either on an assumption of safety. Check databases, caches, queues, sessions, files, temporary directories, object-storage prefixes, ports, browser profiles, external identifiers, static/global state and fixtures. Keep cases serial or independently isolated where competing execution would invalidate their purpose.

Playwright remains `workers: 1` and `fullyParallel: false`. Neither workers nor retries were increased. Repeat isolation-sensitive cases in different orders and under bounded worker counts before claiming reliable parallelism. Report wall-clock improvement separately from aggregate compute cost. Browser directory moves can change file-based discovery IDs and execution order even when file contents are identical; reconcile both projects and shared fixtures before accepting the reorganization.

## Profiling and budgets

The recorded baseline in [Performance baseline](test-performance-baseline-2026-09-09.md) used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6 and two workers. It recorded 1,482 tests / 82,984 assertions, 9:35.23 wall time and 1,131.400 seconds aggregate JUnit duration. The historical Architecture lane separately recorded 63 tests in approximately 1.53 seconds. These are historical results, not current inventory or passing-test claims.

```sh
php artisan test --parallel --processes=2 --log-junit /path/to/profile-junit.xml
```

Main CI writes `storage/logs/phpunit-junit.xml` and retains `phpunit-results` on success or failure when the test phase is reached. Profile slow files/classes/cases from those artifacts. Record immutable revision, exact commands, environment, worker count, failures/skips, cold/warm conditions and repetition count. Separate installation, migrations/fixtures/bootstrap and browser/assets from test execution where measurable. Distinguish cheaper tests, smaller development selections and extra workers.

No comparable after-optimization run is recorded. Do not invent a new performance budget or speedup from directory moves, schema reuse, startup removal or a failing run. Use the baseline as a comparison point until reliable repeated measurements support a budget.

## CI and complete certification

Cache Composer download archives keyed by the lockfile, not vendor trees, generated configuration, databases or test results. Every PHP job still performs locked installation. Architecture CI defers autoload generation with `composer install --no-interaction --no-progress --prefer-dist --no-autoloader`, followed immediately by `composer dump-autoload --optimize --strict-psr`. Normal hooks, package discovery and platform checks remain enabled; review ordering before adding an install hook that requires autoloading. This previously authored optimization still needs runtime/timing verification.

Main CI owns complete PHP and frontend regression, fresh PostgreSQL installation and downstream container/staging/recovery. Keep required security, acceptance, browser and critical integration checks blocking where already required. Specialized gates should supply early owner-specific or unique-configuration checks rather than duplicate entire main-CI lanes. King Perks' targeted build and KingdomMaps' geometry/source checks remain distinct; Gift Code and Intelligence duplicate frontend lanes were removed in earlier work.

PostgreSQL certification must retain the configured parallel lock capacity and verify `fsync`, `synchronous_commit` and `full_page_writes` are enabled before the fresh-install check. Preserve failing exit codes through wrappers/report generation and retain useful diagnostics. Cancellation of superseded work does not replace checks on the final revision.

Review intentional visual changes before updating Playwright snapshots. Never remove scenarios, relax tolerances or regenerate baselines blindly. Dynamic-value normalization must preserve semantic assertions. Final architecture review includes source directories, namespaces, imports, relationships, database ownership, controllers/routes/actions, permissions, transactions, events/listeners, tests, documentation and CI—not merely classes whose names contain Architecture.
