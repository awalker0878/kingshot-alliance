# Test organization: incremental verification

Status: In progress — PR #163 only — 2026-09-09

This records completed slices of the [test organization and performance plan](test-organization-and-performance-plan.md). The standard directory migration and developer command surface are complete; reset-strategy optimization, measured parallelism changes, conservative changed-code selection and final containing verification remain open.

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

Commit `cee026cb5aaad9369f17eff25575f3797f0968c2` updates `docs/codebase/testing.md` with staged validation, domain/file selection, fallback rules, isolation requirements and profiling guidance. Targeted commands are explicitly documented as accelerators, not proof that the complete application passes.

These commands were **not executed in this organization pass**.

## Existing performance baseline

The separately recorded baseline in `test-performance-baseline-2026-09-09.md` used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6 and two workers. It recorded 1,482 tests / 82,984 assertions in 9:35.23 wall time, with 1,131.400 seconds aggregate JUnit test time. The architecture-only lane separately took approximately 1.53 seconds for 63 tests.

That evidence establishes the comparison point and the value of narrow feedback lanes. It does **not** establish an after-optimization speedup for the current standard-layout revision. Comparable post-change measurements remain required once test execution is permitted.

## CI queue and trigger isolation

Commit `84277345faea41dedbbaf63e8818b320e4522c3b` adds a per-ref concurrency group to Intelligence Verification and cancels superseded runs. The job names, triggers and complete existing behavior command remained unchanged in that historical slice.

Commit `bb33c3600476769bb34d53e9edcf986fc1dd3809` narrows the Intelligence workflow's broad `tests/**` pull-request trigger to the exact organized Intelligence and Intelligence-read-model test directories that its backend job executes. Gift Code, King Perks and KingdomMaps workflows already use ownership-specific test triggers. The main `CI` workflow remains unfiltered for pull requests and continues to own the complete PHP regression, so this change removes unrelated specialized-job fan-out rather than weakening application-wide verification.

## Remaining work

The next optimization work must be evidence-led rather than cosmetic:

- profile repeated Laravel/database setup against the recorded baseline;
- identify genuinely isolated tests that still pay application bootstrap cost;
- inspect migration/reset strategy and oversized fixture/factory graphs without weakening PostgreSQL semantics;
- measure bounded worker counts and verify database/cache/queue/session/file/port isolation before increasing parallelism;
- remove any remaining duplicated CI execution only when complete inventory coverage remains explicit;
- implement changed-code selection only with dependency-aware broadening, visible reasons, zero-selection failure handling and tested fallback behavior;
- record comparable post-change wall-clock and aggregate timings;
- reconcile PHPUnit discovery and browser inventory on the final revision and run the required complete verification before declaring the program complete.
