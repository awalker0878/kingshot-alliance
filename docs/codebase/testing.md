# Testing

Status: Current — owner-organized execution tiers — 2026-09-09

Tests are organized by **execution semantics first**, then by the owner of the behavior. A targeted run is a development accelerator, never proof that the whole application passes. [Test navigation and naming](../../tests/README.md) explains where to place, rename, consolidate or split a test.

## Toolchain and verification status

The repository requires PHP 8.5. The inspected dependency artifact contains Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0 and Pint 1.30.4; the browser package is Playwright 1.62.1. Inspect installed versions before choosing commands; a lockfile or historical artifact is not evidence of a developer machine's runtime version.

The current ownership pass starts at `4252615d77044b94b02b3cf0937566628313e858`. Commits `57fe1189`, `417f0b3e` and `c06229f9` move 13 existing classes and split one mixed class into two. The resulting 15 PHP files preserve all 59 affected test methods, their assertions, fixtures and provider bodies. The split adds one source class, not new or duplicated scenarios. Thirteen inverse source comparisons reproduce their originals after reversing only namespace/class/path changes; the split's two method bodies are unchanged. Connector blob/subtree hashes match the prepared sources.

The reconciled source inventory is **285 PHP test files**: 15 Unit, 189 Feature, 53 Integration, 25 Architecture and 3 Frontend. This follows the prior 284-file inventory plus the one class split; it is **not runtime discovery**. No runner configuration, test-selection filter, production behavior, database reset implementation, retry count, coverage threshold or worker count changed in this ownership pass.

Only source inspection, source equivalence and host PHP 8.4.23 syntax checks were performed for these changes. PHP 8.5 target-runtime validation, PHPUnit discovery/execution, browser execution, order/isolation checks and after-change timing remain pending. No tests, migrations, benchmarks or CI dispatch were run. Commits carry `[skip ci]` during the explicit execution hold; that does not satisfy or permanently disable the required gates.

## Suite structure and ownership

`phpunit.xml` defines five disjoint recursive roots. Every PHP test belongs to exactly one; domain folders are not extra overlapping suites.

| Suite | What belongs here | Resources |
| --- | --- | --- |
| Unit | Isolated logic and inert value/interface contracts | Pure PHPUnit; no application bootstrap or database |
| Feature | HTTP, authorization, application behavior, persistence and composed reads | Laravel application and appropriate ordinary isolation |
| Integration | Committed-state, after-commit, infrastructure and transaction behavior | Real PostgreSQL/independent connections where semantics require them |
| Architecture | Ownership, dependencies, source boundaries, reflection and actual application registration | Pure PHPUnit for source/reflection; Laravel for real wiring/routes/scheduler |
| Frontend | PHP-side frontend source contracts | No browser startup; not a substitute for browser journeys |

`tests/Browser` contains Playwright journeys. `tests/Fixtures`, `tests/Support` and `tests/TestCase.php` are support, not execution suites.

Use `Contexts/<Context>/<Capability>`, `ReadModels/<Composition>`, `Workflows/<Workflow>` and `Shared/Infrastructure/<Concern>` below the appropriate tier. The read-model boundary classes now live under [Architecture/ReadModels](../../tests/Architecture/ReadModels/README.md); capability boundaries live under [Architecture/Contexts](../../tests/Architecture/Contexts/README.md). Repository-wide rules and cross-application acceptance matrices remain explicitly cross-cutting instead of being assigned to an arbitrary context.

Frontend contracts are grouped under GameWorld/Players, Alliance/Content and GameWorld/KingdomTransfers. Cross-route throttle behavior is under `Feature/Shared/Infrastructure/Security`; cache namespace and migration-reference harness checks are under `Integration/Shared/Testing`. The separate top-level Feature/Infrastructure and Integration/Infrastructure buckets are gone.

The existing `Integration/Concurrency/Contexts` and `Integration/Concurrency/Workflows` paths remain part of the Integration inventory. Include them in domain selections. `Integration/Schema` is reserved for genuine schema-lifecycle contracts if introduced. Neither label is permission to change engines, mocks, isolation or scheduling.

### Separate source contracts from real wiring

`Tests\Support\RepositoryPath::fromRoot()` locates source files independently of test nesting and does not cache their contents. Source-only classes use `PHPUnit\Framework\TestCase`. Real container, route, middleware, scheduler, encryption and persistence contracts keep `Tests\TestCase`.

Within `Architecture/Contexts/Intelligence/Evidence`, `EvidenceReferenceContractTest` now owns the unchanged family-neutral interface reflection method. `GovernorProgressionEvidenceBindingTest` owns the unchanged real container-resolution method. Both remain in Architecture and the existing Intelligence path selection; only the reflection case stops paying for a Laravel bootstrap. The split is not permission to replace the binding assertion with a mock or source string.

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

# One real application-binding contract
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Architecture/Contexts/Intelligence/Evidence/GovernorProgressionEvidenceBindingTest.php

# Full PHP regression: serial, existing local parallel, or fixed two-worker CI
composer test
composer test:parallel
composer test:ci

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

The earlier migration-reference repair and its six authored regression cases still require execution. Validate mixed `DatabaseTruncation`/`RefreshDatabase` ordering, schema reuse, committed fixture removal, reference mutations, identity handling and failure cleanup before accepting it. The ownership pass does not validate or modify that repair.

## Parallelism and isolation

The existing full CI path uses two PHP workers; the developer parallel command retains its local default. Do not increase either on an assumption of safety. Check databases, caches, queues, sessions, files, temporary directories, object-storage prefixes, ports, browser profiles, external identifiers, static/global state and fixtures. Keep cases serial or independently isolated where competing execution would invalidate their purpose.

Playwright remains `workers: 1` and `fullyParallel: false`. Neither workers nor retries were increased. Repeat isolation-sensitive cases in different orders and under bounded worker counts before claiming reliable parallelism. Report wall-clock improvement separately from aggregate compute cost.

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
