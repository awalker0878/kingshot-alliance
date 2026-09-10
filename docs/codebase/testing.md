# Testing

Status: Current owner-first layout; runtime verification pending.

Start with **what is being tested**, then its execution type. Use `Contexts/<Context>/<Capability>/<Tier>`, `ReadModels/<Composition>/<Tier>`, `Workflows/<Workflow>/<Tier>` and `Shared/<Area>[/<Concern>]/<Tier>` beneath tests. Repository-wide architecture checks live in `System/Architecture`; cross-application acceptance lives in `System/Acceptance/{Feature,Browser}`.

[ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) records the decision. [Test navigation](../../tests/README.md), [Contexts](../../tests/Contexts/README.md), [ReadModels](../../tests/ReadModels/README.md), [shared support](../../tests/Support/README.md) and [Browser testing](browser-testing.md) explain placement and naming. [Performance reporting](test-performance-reporting.md) owns result diagnostics and measurement interpretation.

## Execution views and discovery

The physical layout is owner-first, but PHPUnit exposes five disjoint execution suites. An owner is not an additional overlapping suite.

| Suite | Responsibility and resource boundary | Current source files |
| --- | --- | ---: |
| Unit | Isolated logic and inert contracts; pure PHPUnit without application startup | 17 |
| Feature | Actual HTTP, authorization, validation, encryption, persistence and application interactions | 190 |
| Integration | Committed-state, after-commit, independent connections and infrastructure | 53 |
| Architecture | Ownership/source/reflection contracts and real application registration where required | 28 |
| Frontend | PHP-side frontend source contracts; not browser journeys | 3 |
| **Total PHP** | **Each source file assigned once** | **291** |

These are source-file counts, not discovered or executed case counts. The latest increase from 290 is one existing adapter class split by resource requirements; no scenario was added or duplicated. Browser remains a separate runner with 17 specifications and 12 reviewed PNG baselines. Owner-local Support/Fixtures and the common TestCase are not suites. Keep only execution folders an area needs; concurrency belongs in its `Integration/Concurrency`, and genuine migration-lifecycle contracts in `Integration/Schema`.

`phpunit.xml` explicitly lists each owner/type directory and recursively discovers `*Test.php` within it. New files under an existing directory need no per-file manifest entry. After creating or removing an owner/type directory, synchronize and commit the configuration:

```sh
php scripts/sync-test-suites.php
php scripts/sync-test-suites.php --check
php scripts/verify-test-layout.php
```

These scripts inspect paths/source without loading tests, evaluating providers or bootstrapping Laravel. Synchronization changes only the testsuites block; check mode fails on stale configuration. The guard rejects missing/duplicate directories, invalid or multiple execution types, namespace/file mismatches, duplicate declarations, non-pure Unit bases, ordinary per-test schema rebuilds and tier-first/versioned roots. Empty inventories and suites cannot silently succeed. Do not bypass failures with exclusions.

## Exact development commands

Use a prepared PHP 8.5 environment at the repository root. Named Composer test commands check suite freshness before running; runner arguments are not forwarded into their preflight/config-clear steps. Direct PHPUnit invocations do not invoke that Composer preflight automatically. During an explicit no-test hold, the runner commands below are documented only.

```sh
# Fast PHP feedback across owners
composer test:fast

# One execution type across owners
composer test:unit
composer test:feature
composer test:integration
composer test:architecture
composer test:frontend-contracts

# A capability's PHP tests across its types
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Recruitment

# Its real concurrency contracts
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/Alliance/Recruitment/Integration/Concurrency

# A separately owned composed page and its browser journeys
vendor/bin/phpunit --fail-on-empty-test-suite tests/ReadModels/RecruitmentManagement/Feature
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser

# Full PHP regression: serial, local parallel, fixed two-worker CI
composer test
composer test:parallel
composer test:ci

# Complete PHP source-quality and regression checks
composer check:ci

# Full browser/visual regression
npm run test:visual

# Summarize a result file that already exists; does not run tests
php scripts/summarize-test-timings.php /path/to/existing-junit.xml --limit=15
```

An owner selection includes its PHP files, not separately owned dependencies or browser execution. Never label a targeted run as full-application verification. Keep empty-selection failures visible. There is no authoritative changed-code selector: filename matches, uncertain dependency mappings and zero selections do not certify coverage.

During editing, run directly relevant tests. After a coherent change, include affected owners and dependencies. Before completion, run required complete PHP, frontend, browser, security, acceptance and deployment checks. Avoid duplicate full-suite launches after every edit and competing contributors using the same resources. Broaden for base tests/support/fixtures, providers, authentication/authorization, migrations, transaction helpers, shared persistence, dependencies, runner configuration, CI and selection logic. Uncertain mappings require broader verification.

## Preserve isolation and meaningful integration

Use pure PHPUnit for source/reflection and independent logic. `Tests\Support\RepositoryPath::fromRoot()` resolves repository source regardless of test depth and does not cache contents. Real container, route, scheduler, validation, encryption and persistence contracts retain `Tests\TestCase`. Colocation does not justify a common expensive base or mocks replacing meaningful integration boundaries.

Choose database reset by the contract. An outer rollback transaction is appropriate only when it does not invalidate committed visibility, competing connections, real locks or after-commit behavior. Schema-stable committed-state tests use DatabaseTruncation; DatabaseMigrations belongs only in intentional owner-local `Integration/Schema` contracts. Never change engines, constraints, durability, security settings or rate limits for speed.

Truncation tests extend Tests\TestCase. The existing MigrationReferenceData helper captures actual migration-created plans, entitlements and event catalogue rows only after fresh migration and restores them after cleanup and teardown. Mutable reference tables are not exempt. Snapshots are process-local and keyed by the actual worker database. A prior transactional test without a snapshot can require one additional fresh migration. Never capture unknown fixture history; create fixtures after parent::setUp(), not in earlier fixture-mutating trait hooks. New migration-populated tables require review of reference insertion order and identity handling; unclassified populated tables fail closed.

**The earlier reference-reset repair and its six authored regression cases remain runtime-unverified.** Validate mixed reset-trait ordering, committed fixture removal, reference mutation recovery, identity handling and failure teardown before accepting it. Source reorganization/reporting does not validate or alter that repair.

PHP CI retains two workers; local parallel execution retains its existing default. Playwright retains one worker and non-parallel files. Check databases, caches, queues, sessions, files, temporary/storage prefixes, ports, browser profiles, external identifiers and global/static state before increasing concurrency. Keep serial cases where concurrency changes the meaning of the test. No added retries or weaker assertions are a substitute for isolation. File moves can change IDs and order; source equivalence is not order-independence proof.

## Toolchain and source-only evidence

The required PHP series is 8.5. The inspected dependency snapshot contains Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0, Pint 1.30.4 and browser package Playwright 1.62.1. Recheck installed versions in the environment used for runtime verification; historical artifacts do not establish another machine's toolchain.

The owner-first migration from `b901f527` to `b824797` records 326 moves in the [migration manifest](test-owner-first-migration.json), with an independent [source audit](test-owner-first-source-audit.json). These receipts preserve that checkpoint's file hashes and counts rather than acting as mutable current inventories. PHP changes were constrained to namespaces, equivalent ancestor depths and known references. Browser specs and PNGs retained their bytes. Later owner-local fixture moves preserve bodies/data and update explicit consumers. Preparation-only tooling is no longer on the branch.

The prior cost-separation checkpoint `7009517f` preserves eight original methods: pure Transfer frontend checks, an extracted Alliance Rules lock-source contract and per-method real prerequisite datasets. The prerequisite class's successful paths have 18 dataset loads before and four after, a static operation count rather than elapsed evidence. No cross-test/static cache was introduced, and integrity/tamper reloads remain unchanged.

The reporting checkpoint `88c9e667` separates real notification registration from database delivery setup, adds a result-only timing reporter and makes CI phases observable. Its original six notification methods remain once; subsequent reporter hardening brings it to eighteen authored, unexecuted cases. [Performance reporting](test-performance-reporting.md) records exact files, commits, old-artifact processing and source-check limitations.

The adapter-split checkpoint `6bd54287` keeps seven registry/document-parsing methods under the Gift Code owner without database reset, while the two persisted ingestion methods retain RefreshDatabase. All nine original methods and both helpers are byte-identical and occur once. Both classes retain actual Laravel wiring; only the adapter-only class adds class-local stray-request prevention. The [owner guide](../../tests/Contexts/GameWorld/GiftCodes/README.md) records the mapping and complementary commands. Seven fewer reset requests is a source-derived operation count, not a timing result. Existing owner-local discovery already covers both files.

Commit `311ae1ca` adds the empty-suite failure flag to the existing Gift Code and KingdomMaps CI selections and captures both statuses of Intelligence's runner-to-tee pipeline. A runner failure keeps its exit code; otherwise a failed diagnostic write remains a failure. Selected paths, database engines/settings, job triggers, retries and worker counts are unchanged. PHP 8.5.10 syntax and Pint 1.30.4 checked the two split files; source-method and remote-hash comparisons preserve the original scenarios. Parsed YAML comparison and Bash syntax checks cover the three workflow command changes, not executed failure-path tests. The no-test hold remains in place.

No test runner/discovery, provider evaluation, browser journey, seeder, application migration, benchmark or CI dispatch ran in this continuation. PHP syntax/formatting, path/layout guards, source equivalence, existing-report processing and workflow syntax are not full regression or static-analysis certification. Commits carry `[skip ci]` during the explicit hold; this neither marks required gates passed nor permanently disables them. Complete discovery, order/isolation, report edge cases, hosted CI and full regression remain pending.

## Profiling, budgets and complete certification

The [historical successful baseline](test-performance-baseline-2026-09-09.md) recorded 1,482 tests / 82,984 assertions in 9:35.23 wall time, with 1,131.400 seconds aggregate JUnit duration, PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6 and two workers. These are historical results, not current discovered counts. No comparable successful after-run exists and no new budget or speedup is established.

When execution is authorized, profile with `php artisan test --parallel --processes=2 --log-junit /path/to/profile-junit.xml`. Keep immutable revision, commands, hardware/versions, workers, failures/skips, cold/warm cache conditions and repetitions. Main CI retains raw XML, ranked timings, environment, process wall/CPU data and reporter diagnostics in `phpunit-results`. Separate measured phase overhead from aggregate case time; never subtract parallel aggregate duration from elapsed time as an estimate of setup. See [measurement boundaries](test-performance-reporting.md).

Ordinary feedback stays free of unnecessary coverage instrumentation. No dedicated coverage command is introduced; preserve applicable thresholds and use the explicitly prepared coverage environment when required. Cache dependency downloads, not vendor trees, generated configuration, databases or test outputs. Every PHP job still performs locked installation. Architecture CI's prior no-autoloader install and subsequent strict optimized autoload generation retain normal hooks and platform checks; runtime/timing verification remains pending.

Main CI keeps layout, formatting, static analysis and full regression in the same order, each once, with a guard against drift from the aggregate Composer script. Fresh PostgreSQL installation, dependency/security checks and downstream container/staging/recovery remain blocking. Preserve database durability and lock-capacity checks, failing exits and artifacts. Cancellation does not certify the final revision. Review intended visual changes before changing baselines; never remove scenarios, relax tolerances or blindly regenerate snapshots to obtain green results.
