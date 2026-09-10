# Testing

Status: Current owner-first layout — runtime verification pending — 2026-09-09

Start with **what is being tested**, then its execution type. Tests now use `Contexts/<Context>/<Capability>/<Tier>`, `ReadModels/<Composition>/<Tier>`, `Workflows/<Workflow>/<Tier>` and `Shared/<Area>[/<Concern>]/<Tier>` beneath tests. Repository-wide architecture checks live in `System/Architecture`; cross-application acceptance lives in `System/Acceptance/{Feature,Browser}`. See [test navigation and naming](../../tests/README.md), [Contexts](../../tests/Contexts/README.md), [ReadModels](../../tests/ReadModels/README.md) and [Browser testing](browser-testing.md).

## Execution views and discovery

The physical layout is owner-first, but PHPUnit still exposes five disjoint execution suites. An owner is not an extra overlapping suite.

| Suite | Responsibility and resource boundary | Current source files |
| --- | --- | ---: |
| Unit | Isolated logic and inert contracts; pure PHPUnit without application startup | 16 |
| Feature | Actual HTTP, authorization, validation, encryption, persistence and application interactions | 189 |
| Integration | Committed-state, after-commit, independent connections and infrastructure | 53 |
| Architecture | Ownership/source/reflection contracts plus real application registration where required | 26 |
| Frontend | PHP-side frontend source contracts; not browser journeys | 3 |
| **Total PHP** | **Each file assigned once** | **287** |

Browser is a separate Playwright runner containing 17 specifications, with 12 reviewed PNG baselines. Support, Fixtures and TestCase.php are not suites. Keep only execution folders an owner needs. Concurrency belongs in that owner's `Integration/Concurrency`; genuine migration-lifecycle contracts belong in `Integration/Schema`.

`phpunit.xml` lists each existing owner/type directory explicitly and discovers `*Test.php` recursively within it. New files in an existing directory are discovered without editing an inventory manifest. After adding a new owner/type directory, synchronize and commit the configuration:

```sh
php scripts/sync-test-suites.php
php scripts/sync-test-suites.php --check
php scripts/verify-test-layout.php
```

These scripts read paths/source only; they do not load test classes, evaluate providers or bootstrap Laravel. Synchronization changes only the testsuites block. Check mode fails on stale configuration. The guard rejects missing/duplicate suite directories, unknown or multiple execution types, namespace/file mismatches, duplicate declarations, non-pure Unit bases, ordinary per-test schema rebuilds and reintroduced tier-first/versioned roots. Full source inventory and required suites cannot silently be empty. Do not bypass guard failures with exclusions.

## Exact development commands

Use a prepared PHP 8.5 environment from the repository root. During an explicit no-test hold, the following are documented commands, not executed checks.

```sh
# Fast PHP feedback, across owners
composer test:fast

# A single execution type, across owners
composer test:unit
composer test:feature
composer test:integration
composer test:architecture
composer test:frontend-contracts

# A capability's PHP tests across its types
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Recruitment

# Just that capability's real concurrency contracts
vendor/bin/phpunit --fail-on-empty-test-suite \
  tests/Contexts/Alliance/Recruitment/Integration/Concurrency

# Complementary composed-page and browser coverage
vendor/bin/phpunit --fail-on-empty-test-suite tests/ReadModels/RecruitmentManagement/Feature
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser

# Full PHP regression: serial, local parallel, fixed two-worker CI
composer test
composer test:parallel
composer test:ci

# Full browser/visual regression
npm run test:visual
```

An owner selection includes its PHP files, not the separately owned contexts, read models or workflows it depends on, and not the browser runner. Keep empty-suite failures visible. There is no authoritative changed-code selector. Filename matching, a zero selection or a narrow passing run is not application-wide verification.

While editing, run directly relevant tests. After a coherent change, include affected owners and dependencies. Before completion, run required complete PHP, frontend, browser, security, acceptance and deployment checks. Do not launch duplicate full suites after every edit. Broaden for base tests/support/fixtures, providers, authentication/authorization, migrations, transaction helpers, shared persistence, dependencies, runner configuration, CI and selection logic. Uncertain mappings require broader verification.

## Preserve isolation and meaningful integration

Use pure PHPUnit for source/reflection and independent logic. `Tests\Support\RepositoryPath::fromRoot()` locates repository source regardless of test depth and does not cache its contents. Real container, route, scheduler, validation, encryption and persistence checks retain `Tests\TestCase`. Colocation does not justify sharing expensive setup across otherwise isolated cases or substituting mocks at important boundaries.

Choose database reset strategy by the contract. An outer rollback transaction is appropriate only when it does not invalidate committed visibility, competing connections, real locks or after-commit behavior. Schema-stable committed-state tests use DatabaseTruncation; intentional schema-lifecycle tests may use DatabaseMigrations only under owner-local Integration/Schema. Never change database engines, constraints, durability, security settings or rate limits for speed.

Truncation tests extend Tests\TestCase. The existing MigrationReferenceData helper captures actual migration-created plans, entitlements and event catalogue rows only after a fresh migration and restores them after cleanup and at teardown. Mutable reference tables are not exempt from cleanup. Snapshots are process-local and keyed by the actual worker database. Never capture unknown fixture history as a baseline; create case fixtures after parent::setUp(). A newly populated migration table requires review of reference insertion order and identity handling. Unclassified populated tables fail closed rather than silently losing data.

The earlier reference-data reset repair and its six authored regression cases remain runtime-unverified. Validate mixed reset-trait ordering, committed fixture removal, reference mutation recovery, identity handling and failure teardown before accepting it. This owner-first migration does not modify that implementation.

PHP CI retains two workers; the local parallel command retains its existing default. Playwright retains one worker and non-parallel files. Do not increase concurrency or retries without checking database, cache, queue, session, temporary-file, storage, port, profile and global-state isolation. Directory changes can alter file-based IDs and order, so source equivalence is not order-independence proof.

## Toolchain and source-only migration evidence

The source baseline is `b901f527c4fb533c29ff708af8ebc8da54c7f0a5`; the mechanical owner-first checkpoint is `b824797aea35d2b4e69539b97013f3bce07a6298`. The [migration manifest](test-owner-first-migration.json) records all 326 old/new paths and checkpoint blob hashes: 287 PHP test files, 17 browser specs, 12 PNGs, seven standalone PHP source-verification scripts and three navigation documents. Later documentation cleanup supersedes the three document blobs, not test or snapshot content.

The twelve staged transformations preserve every PHP test's source after only matching namespace, equivalent ancestor depth and known test-reference changes. Assertions, providers, fixtures, class names and resource strategy are not consolidated or deleted. All browser specifications and PNGs retain their original bytes. PHPUnit suite names, Composer command names, coverage settings, projects and worker/retry counts remain unchanged. CI receives path updates, including one non-overlapping Intelligence owner selection instead of four old tier-prefixed roots.

PHP 8.5.10 syntax and dependency-free source-layout checks completed at every stage. All twelve prepared Git trees matched independent local reconstruction; the connector applied workflow-file entries and committed the exact matching trees. Source-preparation run `34419861324` retained artifact `10130485744`. This was not a regression run: no PHPUnit/Playwright discovery, test execution, application migrations or benchmarks were performed. Preparation-only tooling is removed from the final branch. Commits use [skip ci] during the explicit hold, without marking mandatory gates passed or permanently disabling them.

Final local source checks also passed: PHP 8.5.10 syntax and Pint 1.30.4 formatting for all 297 moved/new PHP files, TypeScript 5.9.3 parsing of 17 specifications plus the Playwright configuration, workflow YAML parsing, relative links in test READMEs and documentation links across 262 files. No spec or source-certification script was executed by these checks. Static Browser path matching reconciles all 17 specifications; it is not Playwright discovery. Non-suite PHPUnit configuration, dependency lockfiles, shared TestCase and the migration-reference helper are byte-for-byte unchanged. Concurrent Composer wrapper changes at `03fec131` were preserved: named test commands check suite-path freshness first and keep extra runner arguments away from setup/preflight commands. Those wrappers have not been executed during this hold.

The inspected dependency artifact contains Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0, Pint 1.30.4 and Playwright 1.62.1. Installed versions must still be checked in the environment used for eventual runtime verification; historical artifacts do not establish another machine's toolchain. Final discovery, order/isolation, full regression and browser verification remain pending.

## Profiling, budgets and complete certification

The [historical performance baseline](test-performance-baseline-2026-09-09.md) used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6 and two workers. It recorded 1,482 tests / 82,984 assertions, 9:35.23 wall time and 1,131.400 seconds aggregate JUnit duration. These are historical results, not current discovered counts or passing-test claims. No comparable after-optimization result exists; owner-first navigation is not itself a measured runtime speedup.

Main CI retains storage/logs/phpunit-junit.xml as phpunit-results on success/failure when the test phase is reached. Profile using `php artisan test --parallel --processes=2 --log-junit /path/to/profile-junit.xml` only when execution is authorized. Record immutable revision, commands, environment, workers, failures/skips, cold/warm conditions and repetitions. Separate setup from test duration where measurable, and distinguish cheaper tests, smaller development selections and extra-worker compute. Do not invent budgets or compare failing runs as speedup evidence.

No dedicated repository coverage command is introduced. Keep normal feedback free of unnecessary instrumentation and preserve required coverage thresholds. Cache dependency downloads, not vendor trees, generated configuration, database state or test outputs. Every PHP job still performs locked installation. Architecture CI's earlier no-autoloader install followed by strict optimized autoload generation retains its normal hooks and platform checks; its runtime/timing verification is still pending.

Main CI continues to own full PHP/frontend regression, fresh PostgreSQL installation and downstream container/staging/recovery. Keep required security, acceptance, browser and critical integration gates blocking. Preserve PostgreSQL durability and lock-capacity checks, failing exit codes through reporting wrappers and useful artifacts. Superseded-run cancellation does not certify the final revision. Review intentional visual changes before updating snapshots; never weaken scenarios or tolerances to obtain a green run.
