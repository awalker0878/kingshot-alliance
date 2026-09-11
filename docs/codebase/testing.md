# Testing

Status: Owner-first testing-system work is implemented and verified on 2026-09-10. All nine normal workflows passed at `e6f29ebbd40687c38a43554facb3ec9ef872ea8c`, using GitHub's test checkout `4485b87f0ab5aef0f93d2f70f8ae6b088697c22b`. Other work in PR #163 is incomplete; keep the PR draft and unmerged.

Start with the owner, then execution type: `Contexts/<Context>/<Capability>/<Tier>`, `ReadModels/<Composition>/<Tier>`, `Workflows/<Workflow>/<Tier>` and `Shared/<Area>[/<Concern>]/<Tier>`. Repository-wide architecture lives in `System/Architecture`; cross-application acceptance lives in `System/Acceptance/{Feature,Browser}`. Create only the types an owner needs.

[ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) records the decision. [Test navigation](../../tests/README.md), [shared support](../../tests/Support/README.md), [Browser testing](browser-testing.md), [performance reporting](test-performance-reporting.md) and the [runtime validation report](test-validation-2026-09-10.md) contain operating rules and evidence. Earlier source-only receipts remain historical; the execution hold has been lifted.

## Execution views and discovery

Owner folders provide navigation, not overlapping suites. PHPUnit retains five disjoint views:

| Suite | Resource boundary | PHP files | Expanded cases |
| --- | --- | ---: | ---: |
| Unit | Independent logic with pure PHPUnit, no application startup | 18 | 125 |
| Feature | Actual HTTP, validation, authorization, encryption, persistence and composed behavior | 191 | 800 |
| Integration | Committed state, independent connections, locking, after-commit and infrastructure | 53 | 512 |
| Architecture | Source/reflection and actual application registration where required | 29 | 75 |
| Frontend | PHP-side frontend source contracts, not browser journeys | 3 | 10 |
| **PHP total** | **Each file and case assigned once** | **294** | **1,522** |

Playwright separately discovers **62 cases in 17 specs**, with the existing **12 PNG baselines**. Node separately runs **11 source contracts in two owner-local files**. Node contracts may share Unit/Frontend directory names but are neither PHP nor Playwright cases. Support/Fixtures are not suites. Concurrency belongs in owner-local Integration/Concurrency; intentional migration lifecycle belongs in Integration/Schema.

`phpunit.xml` lists owner/type directories and recursively discovers `*Test.php`. New files under an existing directory need no per-file registry. After adding or removing an owner/type directory:

```sh
php scripts/sync-test-suites.php
php scripts/sync-test-suites.php --check
php scripts/verify-test-layout.php
```

These scripts inspect paths without loading tests or providers. Synchronization changes only the testsuites block. Guards reject stale/missing/duplicate directories, unassigned files, namespace/file mismatches, duplicate declarations, non-pure Unit bases, inappropriate schema rebuilds and tier-first roots. Unexpectedly empty selections fail. Do not hide guard failures with exclusions.

## Commands and staged validation

Use locked dependencies with **PHP 8.5, Node 24 and npm 11**. Check installed patch versions instead of assuming an artifact matches the current machine. Composer argument isolation requires Composer 2.8 or newer; hosted verification used 2.10.3.

```sh
# Fast PHP feedback: Unit + Architecture + Frontend
composer test:fast

# One PHP execution type
composer test:unit
composer test:feature
composer test:integration
composer test:architecture
composer test:frontend-contracts

# One owner across its PHP types
vendor/bin/phpunit --fail-on-empty-test-suite tests/Contexts/Alliance/Recruitment

# A separately owned composition and browser journeys
vendor/bin/phpunit --fail-on-empty-test-suite tests/ReadModels/RecruitmentManagement/Feature
npm run test:visual -- tests/ReadModels/RecruitmentManagement/Browser

# Independent Node source contracts; no browser startup
npm run test:source-contracts

# Complete PHP: serial, existing local parallel, fixed two-worker CI
composer test
composer test:parallel
composer test:ci

# Complete PHP quality/regression, frontend quality/build and browser checks
composer check:ci
npm run check
npm run test:visual

# Existing result analysis; does not rerun tests
php scripts/summarize-test-timings.php /path/to/existing-junit.xml --limit=15
```

Composer commands check suite freshness and keep runner arguments away from preflight/config-clear commands. Direct PHPUnit does not run that preflight automatically. Mandatory `npm run check` includes the Node contracts. Playwright discovers owner-local Browser/**/*.spec.ts, not Node *.test.ts files.

During editing, run relevant files. After a coherent change, include affected owners and dependencies. Before acceptance, run complete PHP/frontend/browser/security/deployment gates. An owner run does not certify separately owned contexts, read models, workflows or browser behavior. Avoid duplicate full-suite launches after every edit and contributors contending for resources.

No automatic changed-code selector is enabled. Select owners explicitly and broaden for shared helpers, providers, authentication/authorization, migrations, transaction/persistence infrastructure, dependencies, runner configuration and CI. Uncertain mappings require broader verification, not empty success. Normal feedback does not enable coverage instrumentation. Preserve applicable coverage thresholds; case counts are not a line-coverage percentage.

## Integration and isolation

Independent source/reflection tests use pure PHPUnit. RepositoryPath locates files without Laravel or caching. Real route/container/scheduler, validation, encryption, authentication and persistence checks retain actual resources. The [Gift Code](../../tests/Contexts/GameWorld/GiftCodes/README.md) and [KingdomMaps](../../tests/Contexts/GameWorld/KingdomMaps/README.md) guides record mixed-resource separations. Unsaved adapter contracts retain Laravel and HTTP fixtures with class-local stray-request prevention; persisted ingestion keeps database verification.

Rollback transactions are appropriate only when they do not invalidate committed visibility, independent connections, locks or after-commit behavior. Schema-stable committed-state tests use DatabaseTruncation. DatabaseMigrations belongs only in genuine Integration/Schema contracts. Never change engines, constraints, durability, production timeouts, rate limits or security controls for speed.

Truncation tests extend TestCase. MigrationReferenceData snapshots the five tables actually populated by the complete migration chain and restores exact rows and the entitlement identity sequence after cleanup/teardown. Current migrations intentionally leave event_metric_definitions empty: it remains ordinary mutable data subject to truncation and dirty-baseline rejection, not exempt reference data. Its regression inserts a metric, rejects it as reference data and verifies removal after reset. Never capture unknown fixture history; create fixtures after parent setup. New populated migration tables require reviewing the explicit reference contract.

The repaired reset path has targeted, mixed-order and complete-suite evidence in the dated report. Process-local snapshots are keyed by actual worker databases. This is not proof for arbitrary new fixture hooks or more workers. Keep two CI PHP workers and one non-parallel browser worker until measurements justify changes. Isolate databases, caches, queues, sessions, temporary files, storage prefixes, ports, profiles and external identifiers. Keep serial execution where concurrency invalidates the contract.

Inertia AJAX tests must send the actual asset version when verifying page responses. Do not disable middleware to remove build-state sensitivity. Corrected rules/debrief requests passed with and without the manifest; complete local PHP also passed with built assets.

## Measurement and CI policy

The dated report records revisions, conditions, defects and discovery mapping. A six-run class comparison measured median **37.57 to 14.70 seconds** for per-case migration versus schema reuse, with the same **26 cases/90 assertions** passing every trial at one worker. That is a **60.9% class-level wall reduction**, not a whole-application percentage. Complete local default/random-order runs passed 1,507 cases in **313.50/315.11 seconds** at two workers. Hosted conditions differ; do not compare them directly to local or treat the historical 575.23-second run as a matched baseline.

For this local environment, roughly **two seconds for fast PHP** and **five to six minutes for complete two-worker execution** are investigation references, not portable timeouts or relaxed thresholds. Keep existing CI timeouts until comparable repeated hosted evidence supports a budget. Selecting fewer cases or adding workers is not the same as reducing their cost.

Main CI runs layout, formatting, static analysis and complete regression once each, rejects drift from check:ci and checks layout before dependencies. Dependency/security checks, fresh durable PostgreSQL, container/staging/recovery and visual gates remain blocking. Cache download archives, not vendor/database/test state. Architecture installation generates autoload metadata once while retaining strict PSR checks and normal hooks.

The phpunit-results artifact preserves raw JUnit, ranked outcomes/timings, actual checkout revision and versions, process wall/CPU/memory and reporter diagnostics. Runner and writer failures remain visible. Missing results after attempted execution are failures, not empty success. Never subtract parallel aggregate case duration from wall time to estimate setup. Record cache conditions and repetitions, separating measurable setup phases.

Only intentional, reviewed rendered changes justify fingerprint or snapshot updates. The [acceptance review](acceptance-baseline-review-2026-09-10.md) records all seven surfaces, stronger raw semantic checks and bounded normalization. Both viewports passed twice with retries disabled; all twelve PNG baselines remained unchanged. The final normal Visual Regression run then passed all 62 cases, while normal CI passed 1,507 PHP cases with zero failures, errors or skips. Exact workflow IDs, the tested checkout, measurements and residual limits are in the [validation report](test-validation-2026-09-10.md). These results complete testing-system verification only, not the other unfinished work in PR #163; the PR remains draft and unmerged.
