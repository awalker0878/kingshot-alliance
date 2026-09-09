# Test organization: incremental verification

Status: In progress — PR #163 only — 2026-09-09

This records completed slices of the [test organization and performance plan](test-organization-and-performance-plan.md). The standard directory migration, developer command surface, committed-state reset optimization and first CI setup reductions are implemented. Comparable post-change timing, isolation/order verification, conservative changed-code selection and final containing verification remain open.

## Browser directory migration

Parent commit: `84277345faea41dedbbaf63e8818b320e4522c3b`.

Deterministic path mapping: every file below `tests/v3/Visual/` moves to the same relative path below `tests/Browser/`. The old directory is removed. Both locations use Git tree `4c9636f89857175d8d6c3b935aca14e7cb6cf0d0`: all 17 specifications and 12 reviewed PNG snapshots are byte-for-byte unchanged. No snapshot was regenerated, no assertion was removed, and browser retries/workers remain unchanged.

Playwright configuration, the current source-certification check and the affected documentation now refer to `tests/Browser`.

Local verification used the locked source/dependency artifact from `f975432e742882f76353d422238511901fd7b12a`, whose test sources are unchanged at the parent commit, with PHP 8.5.10, PHPUnit 12.5.33 and Node 24.20.0:

- `playwright test --list --reporter=json`: 62 cases before and after; equal multisets of project, suite and test title, with no discovery errors.
- `phpunit --list-tests-xml`: 283 PHP classes and 1,482 test identities before and after; the complete identity multiset is unchanged.
- `php tests/v3/Architecture/verify-final-source.php`: pass.
- `vendor/bin/phpunit tests/v3/Architecture`: 63 tests / 69,609 assertions pass; local elapsed test time 1.561 seconds.
- `node scripts/check-documentation-links.mjs`: 258 files checked before this progress note was added.

These are historical discovery, source and architecture checks from that browser-move slice, not a new claim that the current branch's entire behavior or rendered browser suite passes.

## PHP standard-layout migration

Organization commit: `6580bc16c287ab3d5b4da5621a4a5d858b5761fe`.

The legacy `tests/v3` root is removed. PHP tests now live in five disjoint PHPUnit roots with domain ownership retained beneath them:

| Suite | PHP test classes at migration | Primary execution semantics |
| --- | ---: | --- |
| Unit | 10 | Pure PHPUnit; no application bootstrap/database |
| Feature | 194 | Laravel/HTTP/application behavior with ordinary isolation |
| Integration | 52 | Committed-state, database/infrastructure, after-commit and concurrency semantics |
| Architecture | 24 | Source/reflection/route/dependency contracts |
| Frontend | 3 | PHP-side frontend/source contracts without a browser |
| **Total** | **283** | |

`tests/Browser`, `tests/Fixtures`, `tests/Support` and `tests/TestCase.php` remain separate from those five PHPUnit roots. The exact old-to-new mapping, source blob and classification reason for each migrated file is retained in `docs/codebase/test-layout-migration.json`.

The migration also rewrites namespaces, imports, relative source paths, executable workflow selections, PHPUnit suite configuration and current documentation references. `scripts/verify-test-layout.php` guards the resulting topology and rejects legacy versioned roots, stale executable `tests/v3` references, namespace/path drift, duplicate declarations and PHP tests outside a configured suite.

### No-test structural preparation evidence

Per the current instruction for this slice, **no tests were executed while applying the PHP standard-layout migration**.

Before publishing the organization commit, a temporary preparation workflow applied the deterministic migration to the then-current source and performed only structural/file reconciliation. It reported 283 source test classes and the 10/194/52/24/3 distribution above, verified every manifest destination existed and every old path was gone, and ran `git diff --check`. It produced expected Git tree `5e7eb6446dbfba4da982b9081da75376078a60ed` with `tests_executed: false`.

The GitHub connector independently reconstructed the workflow-protected entries and produced that exact expected tree hash before the concurrently added performance-baseline document was layered on top. The temporary preparation script/workflow were then removed from the proposed application tree. This is structural evidence only; it is not PHPUnit discovery or behavior verification.

## Developer execution surface

Commit `4743385ebeca8ad65717868bdae75859d73dcc66` adds explicit Composer commands for the new suites:

- `composer test:fast` — Unit + Architecture + Frontend contracts;
- `composer test:unit`;
- `composer test:feature`;
- `composer test:integration`;
- `composer test:architecture`;
- `composer test:frontend-contracts`;
- existing `composer test` and `composer test:parallel` remain the complete PHP regression paths.

Commit `cee026cb5aaad9369f17eff25575f3797f0968c2` updates `docs/codebase/testing.md` with staged validation, domain/file selection, fallback rules, isolation requirements and profiling guidance. Commit `d08aac32654bc0532c1f2aa23d5cb5516c39e569` makes all named suite commands fail closed on an unexpectedly empty suite and adds CI-only full regression commands that write JUnit results.

Targeted commands remain accelerators, not proof that the complete application passes. These commands were **not executed in the current no-test optimization pass**.

## Existing performance baseline

The separately recorded baseline in `test-performance-baseline-2026-09-09.md` used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6 and two workers. It recorded 1,482 tests / 82,984 assertions in 9:35.23 wall time, with 1,131.400 seconds aggregate JUnit test time. The architecture-only lane separately took approximately 1.53 seconds for 63 tests.

That evidence establishes the comparison point and the value of narrow feedback lanes. It does **not** establish an after-optimization speedup for the current revision. Comparable post-change measurements remain required once test execution is permitted.

## Committed-state database reset optimization

The baseline inventory contained 50 PHP classes using Laravel `DatabaseMigrations`, whose reset contract rebuilds the schema for every test method. Those classes needed committed rows, after-commit visibility or independent PostgreSQL connections, so replacing them with an outer rollback transaction would weaken the behavior under test.

The optimized reset strategy uses Laravel `DatabaseTruncation` for those schema-stable contracts instead. It retains committed database state and real PostgreSQL connection/lock semantics while allowing a migrated schema to be reused within each test process and clearing table data between tests.

- `91a0a75b33c22c9c8930f1d10a25fbdbfab9d5f7` converts the measured hot spot `FinalizedAccountMutationV3Test` after source inspection confirmed no schema mutation.
- `9b4f8b70bad996001e7a90ca059cf568048dbf0c` applies a deterministic no-test source scan to the remaining 47 Integration candidates. All 47 passed the schema-mutation blocker screen and were converted; exact source/result blob mappings are retained in `test-database-reset-migration.json`.
- `97aae76e15f6ed891ee68e033a2e1f24f0e273b2` and `4707a766e3602c439a35e512d31c3e11e35f47bc` convert the two HTTP Feature exceptions after direct review confirmed they are also schema-stable.
- `62d9db86f1d1d55a5df62d78a71eae2387104f3c` prevents ordinary tests from reintroducing `DatabaseMigrations`; future intentional schema-lifecycle tests must live under `tests/Integration/Schema/`.

Thus all 50 classes that previously paid per-test schema rebuild cost now use truncation-based committed-state cleanup. This is an implementation claim only. No post-change test run, order-isolation run or speedup percentage is claimed yet.

## CI queue, trigger and setup optimization

Commit `84277345faea41dedbbaf63e8818b320e4522c3b` adds per-ref cancellation to Intelligence Verification. Commit `bb33c3600476769bb34d53e9edcf986fc1dd3809` narrows its broad `tests/**` trigger to the exact organized Intelligence-owned paths.

Further no-test CI cleanup now includes:

- `94275132880a352ac8d0531de3368e7f160f58b8`: Architecture Verification no longer provisions/migrates PostgreSQL, boots routes separately or repeats full-repository PHPStan; the mandatory main CI still owns fresh PostgreSQL, full static analysis and complete regression.
- `242750c953689bfbd9729634dfcf7ce06643669b` and `a3a4d7c0d15b5ee664d0eaf2459061d126ca0032`: remove Gift Code and Intelligence frontend jobs that were strict subsets of the mandatory main frontend lint/format/type/build job, and remove their frontend-only specialized triggers.
- `2f451c75a02107fc6d0ed62c97742a57172edd28`: add one lock-aware Composer package-download cache action. It caches Composer archives only, never `vendor/`, test state or generated application configuration. Architecture, Gift Code, Intelligence, King Perks, KingdomMaps, Visual Regression and main CI now use it.
- `17e61dc8e42e9012dcff143fdad696bf98fb9199`: main PostgreSQL starts with `max_locks_per_transaction=256` through `POSTGRES_INITDB_ARGS` instead of changing the setting, restarting the service and polling it back to readiness. CI still verifies that lock setting plus `fsync`, `synchronous_commit` and `full_page_writes`, then performs the required clean `migrate:fresh` installation check.
- `3cda156b962bb67e59fc3988fae8ee62a0d319d0`: main full PHP verification writes and always retains a JUnit artifact so future wall-clock and aggregate test timing comparisons can be tied to an immutable revision.

Gift Code, King Perks and KingdomMaps keep ownership-specific backend/unique-contract gates. King Perks' targeted frontend build, KingdomMaps geometry/source validation, browser visual verification, security scanning and staging/recovery remain mandatory where already required. Main `CI` remains unfiltered for pull requests and continues to own the complete PHP and frontend regressions.

## Parallelism intentionally unchanged

`playwright.config.ts` remains `workers: 1` with `fullyParallel: false`; PHPUnit/ParaTest worker count has not been increased. No parallelism increase will be made until database, cache, queue, session, file, port, browser-profile and global-state isolation are measured/repeated. Existing retries were not increased to mask failures.

## Remaining work

The next optimization work must use execution evidence rather than assumption:

- run the current layout/discovery guards and reconcile the final PHP/browser inventory;
- measure the new committed-state reset strategy under the same PHP/PostgreSQL/two-worker conditions as the baseline;
- repeat isolation-sensitive and concurrency cases in differing orders before treating truncation reuse as proven;
- use the newly retained JUnit output to identify the post-reset slow classes and then inspect oversized fixture/factory graphs or repeated application setup only where timing still justifies it;
- measure bounded worker counts and verify database/cache/queue/session/file/port isolation before increasing parallelism;
- implement changed-code selection only with dependency-aware broadening, visible reasons, zero-selection failure handling and tested fallback behavior;
- record comparable post-change wall-clock and aggregate timings, including any compute trade-off;
- run the required complete PHP, frontend, browser, security and deployment verification before declaring the program complete.
