# Testing

Status: Current — standard test layout — 2026-09-09

The test system is organized by **execution semantics first** and by domain ownership underneath those roots. A targeted or fast run is a development accelerator; it is never evidence that the complete application passes.

The authoritative PHP inventory is split into five disjoint PHPUnit suites in `phpunit.xml`. Browser tests remain a separate Playwright surface. `scripts/verify-test-layout.php` guards the suite topology, namespace/path alignment, legacy `tests/v3` references and accidental undiscovered PHP test files.

## Observed toolchain

The repository requires PHP 8.5 and currently declares PHPUnit `^12.5.23` plus ParaTest `7.20.0`. The measured 2026-09-09 baseline used PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0 and PostgreSQL 18.6. Browser verification uses Playwright 1.62.1 from `package.json`.

Those baseline versions and timings are evidence from the recorded baseline; they are not a claim that every developer machine or later CI image has identical patch versions.

## Suite structure

| Path / suite | Purpose | Resource expectations |
| --- | --- | --- |
| `tests/Unit` / `Unit` | Isolated PHP logic and inert contracts | Pure PHPUnit; no Laravel application bootstrap or database requirement |
| `tests/Feature` / `Feature` | HTTP, authorization, application behavior, persistence and read-model interactions | Laravel application; ordinary test isolation |
| `tests/Integration` / `Integration` | Committed-state persistence, lifecycle, after-commit, real infrastructure and transaction semantics | Real PostgreSQL semantics where required; do not replace with a different engine for speed |
| `tests/Integration/Concurrency` | Multi-connection locking, ordering and concurrency contracts | Real independent PostgreSQL connections; keep serial unless isolation and semantics are proven safe |
| `tests/Architecture` / `Architecture` | Source, reflection, route, dependency and boundary contracts | Primarily source/application-structure checks; should not rerun the full database-backed suite |
| `tests/Frontend` / `Frontend` | PHP-side frontend/source contracts that do not need a browser | No Playwright browser startup |
| `tests/Browser` | End-to-end and visual user journeys | Playwright/browser/runtime assets only when a browser is genuinely required |
| `tests/Fixtures`, `tests/Support`, `tests/TestCase.php` | Shared test infrastructure | Not independent PHPUnit suites |

The standard-layout migration records the exact old-to-new file mapping in `docs/codebase/test-layout-migration.json`. At migration time the 283 PHP test classes were assigned as 10 Unit, 194 Feature, 52 Integration, 24 Architecture and 3 Frontend classes.

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

A domain can span more than one execution root. When a change touches both pure logic and persistence, run the relevant `Unit`, `Feature` and/or `Integration` paths rather than assuming one directory represents the complete domain.

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

Keep transaction/concurrency tests serial where parallel execution would alter the behavior being verified. Production-equivalent PostgreSQL constraints, locks, transactions, authentication and security boundaries must not be weakened to improve timing.

The recorded baseline used two workers with PostgreSQL durability settings enabled. Use comparable worker counts and environment settings for before/after performance claims, and report wall-clock time separately from aggregate worker/test time.

## Profiling and performance baseline

The existing reproducible PHP baseline command was:

```sh
php artisan test --parallel --processes=2 --log-junit /path/to/baseline-junit.xml
```

At the recorded source revision it completed 1,482 tests / 82,984 assertions in 9:35.23 wall time. Aggregate JUnit test duration was 1,131.400 seconds. The architecture-only lane separately completed 63 tests in approximately 1.53 seconds, demonstrating the cost of making a narrow architecture gate rerun unrelated database-backed behavior.

Until an equivalent post-optimization run is recorded, treat these as the comparison baseline rather than inventing a new performance budget. When profiling, retain JUnit/timing artifacts, identify slow classes/files and separate setup cost from actual test execution where possible. Record revision, environment, worker count and cold/warm conditions with every timing claim.

## Architecture and behavior verification

Architecture tests must continue deriving rules from the architecture rather than maintaining a second hardcoded capability registry. Important boundaries include context ownership, cross-context imports, write/read separation, HTTP adapter responsibilities, transaction ownership, authorization and persistence rules.

Behavior verification must continue protecting identity, authority, scope, transactions, concurrency, retry/idempotency and business invariants. Moving a test between suites changes its execution ownership, not the behavior it is expected to protect.

## Visual regression baselines

Playwright visual baselines may be refreshed only when the rendered change is intentional and has been visually reviewed. Do not delete cases, relax tolerances or blindly regenerate snapshots to obtain a green run. Dynamic values may be normalized only where the semantic assertions still verify the underlying behavior.

## Fresh-install and full certification

The primary backend verification must continue proving a clean PostgreSQL installation with the repository migrations. A standard folder layout does not replace fresh-install, security, acceptance, static-analysis or browser verification.

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
