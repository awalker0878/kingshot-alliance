# Test organization: incremental verification

Status: In progress — PR #163 only — 2026-09-09

This records completed slices of the [test organization and performance plan](test-organization-and-performance-plan.md). It does not close the remaining PHP classification, reset-strategy profiling or final verification work.

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

These are discovery, source and architecture checks, not a new claim that the entire behavior suite or rendered browser suite has passed. Containing CI and browser execution remain required. No full-suite speedup percentage is claimed.

## CI queue isolation

Commit `84277345faea41dedbbaf63e8818b320e4522c3b` adds a per-ref concurrency group to Intelligence Verification and cancels superseded runs. The job names, triggers and complete existing behavior command remain unchanged in that slice. Local YAML/concurrency validation passes.
