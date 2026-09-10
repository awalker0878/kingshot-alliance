# Testing-system runtime validation — 2026-09-10

The execution hold was lifted for this validation. The agreed structure remains [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md). This report distinguishes executed checks, controlled measurements and limitations. Final normal CI must cover the containing revision; its immutable checkout and run results are recorded in PR #163. The PR remains draft and must not be merged by this work.

## Environment and source

Validation started at `f49a8332b953e924193d2aa3e7b09cb127682a57`. A read-only export at `c3de8d2e` reconciled 2,950 tracked blobs, including export-ignored tests. Fixes were committed through the GitHub connector without force updates. Temporary source/copy preparation tooling was removed after use; the focused browser workflow is removed before final normal CI.

Installed local versions: PHP 8.5.10, Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0, Pint 1.30.4, Node 24.20.0, TypeScript 5.9.3, Playwright 1.62.1, PostgreSQL 18.6. Composer package metadata matched the lock; hosted Composer was 2.10.3. The default host PHP 8.4 was not used for PHP acceptance.

PostgreSQL used an isolated loopback instance and separate test/worker databases. fsync, synchronous_commit and full_page_writes stayed on; max_locks_per_transaction=256. Local Chromium navigation was blocked by host policy, so browser checks used the hosted environment instead of bypassing that restriction.

## Runtime defects corrected

| Commit | Correction and preserved boundary |
| --- | --- |
| `70706069` | Six isolation setup errors revealed that event_metric_definitions was incorrectly required to have migrated rows. Current migrations intentionally leave it empty. Snapshot only the five populated reference tables; metrics still undergo normal truncation and dirty-baseline rejection. A real insert/reject/reset regression verifies this. |
| `81e90a67` | Normal visual CI passed 60/62 cases; both acceptance matrices used nonexistent command/brief URLs. Navigate to the actual dashboard regions, assert HTTP success and await link destinations. Keep all seven surfaces. |
| `8bd15063` | Built assets exposed four unexpected HTTP409 failures. Five rules/debrief requests now send the actual Inertia version. Keep middleware and authorization/status/content/telemetry assertions. |
| `e21c4f41` | Fourteen candidate translation references rendered literal keys. Reconnect existing catalogue labels without altering forms, actions or authorization; add an owner-local source regression. |
| `75f4b709` | Replace broad DOM mutation with pure normalization of concrete IDs and validated formatted dates. Keep labels, amounts, levels, counts and relative-age/freshness text. Add raw semantic assertions and ten source tests. |
| `74d14b34` | Review fourteen screenshots/text captures before replacing obsolete fingerprints for the same seven surfaces. Capture screenshots on mismatch, retain raw/normalized text, and leave all twelve existing PNG baselines unchanged. |

The label correction fixes an observed defect, not production behavior to accelerate tests. The [acceptance review](acceptance-baseline-review-2026-09-10.md) records the protected facts and rendered review.

## Executed results

| Check | Conditions | Result |
| --- | --- | --- |
| Fast PHP | Current PHP; Unit + Architecture + Frontend; one worker | 209 tests / 71,983 assertions; runner 1.450s, process wall 1.82s; no failures/skips. |
| Reference/cache isolation | `70706069`; durable PostgreSQL; one worker | 10 tests / 44 assertions; 5.832s; no failures/skips. |
| Affected owners | `70706069`; randomized seed163 | 129 tests / 3,744 assertions; 13.287s; no failures/skips. |
| Mixed reset/concurrency | `70706069`; two workers, seed164 | 37 tests / 147 assertions; 19.552s; no failures/skips. |
| Rules/debrief AJAX | `8bd15063`; asset manifest present, then absent | Both pass 5 tests / 18 assertions; 2.669s and 2.230s. |
| Complete local PHP | `8bd15063` PHP source; two workers; fresh databases/process, built assets, warm source/dependency filesystem | 1,507 tests / 83,068 assertions; 313.50s wall; zero failures/errors/skips. |
| Complete local PHP, randomized | Same PHP source; two workers; seed20260910; separate fresh databases; built assets | 1,507 tests / 83,068 assertions; 315.11s wall; zero failures/errors/skips. |
| Node source contracts | `75f4b709`, unchanged at `74d14b34`; Node24 | Eleven passing cases in two files; final repeat approximately 112ms. |
| Repeated browser acceptance | `74d14b34`; run34476453647; both viewports twice, retries=0 | Four passes; no flaky/skipped/failing cases; 60.747s total. |
| Actual Composer forwarding | Same hosted run; Composer2.10.3 | Filter/report args reach PHPUnit: 18 reporter tests / 51 assertions pass. Empty filter exits1. |

PHP implementation/test bodies after `8bd15063` were unchanged by the subsequent browser/Node/copy commits through `74d14b34`. Randomized success verifies the recorded seeds/workers, not every possible schedule. Local times exclude dependency installation.

Normal hosted CI **34469136878**, head **70706069**, checkout **ad36e713a65a6cc498e3f03e129c1b6f442ffe13**, passed complete PHP (1,507/83,068 with no failures/skips), Pint, PHPStan, Composer validation/audit, fresh durable database installation, complete frontend checks/build, deployment, backup/restore and image scanning. Architecture, Intelligence, Gift Code, KingdomMaps, King Perks, CodeQL and dependency review also passed at that checkpoint. Its visual run passed 60/62 and led to the acceptance repair; it was not a complete visual pass.

Artifact **10149366260** (`phpunit-results`) records test:ci wrapper wall **901.56s**, child user/systemCPU **144.57/39.49s**, maximumRSS **239,668KiB**, aggregate JUnit time **1,768.817741s**. Integration contributed **1,549.161643s across512 cases**, the largest remaining category. Hosted/local hardware and cache conditions differ: do not treat their durations as matched before/after measurements.

## Discovery reconciliation

Actual pre-owner discovery at **b640ee571e3f9a5e4074c91bb67064b0b7623afa** found **1,488 expanded PHP identities**. All are retained after explicit class/namespace/path mappings, including provider labels. Current **1,507** equals those1,488 plus **18 reporter cases and one metric-reset case**. None disappeared or was combined.

| PHP view | Files | Expanded cases |
| --- | ---: | ---: |
| Unit | 18 | 125 |
| Feature | 190 | 786 |
| Integration | 53 | 512 |
| Architecture | 28 | 74 |
| Frontend | 3 | 10 |
| **Total** | **292** | **1,507** |

Each file is assigned once. Browser reconciliation preserves **62 title/project identities in17 specs** and all **12 PNG baselines**. Eleven new Node cases are a separate mandatory frontend execution path. The older timing baseline predates six pre-owner cases and records1,482, so it is not the discovery baseline. No line-coverage percentage was collected; identity/assertion counts are not coverage percentages.

## Controlled reset-strategy comparison

Base source **70706069**; class `tests/Contexts/Accounts/Identity/Integration/Concurrency/FinalizedAccountMutationV3Test.php`; SHA-256 `3ba7a876e312547453c28d7106dcc7e7237f4461e11c2b9e3527622f13d01908`.

The separate control changes only the DatabaseTruncation import/use to DatabaseMigrations. Both arms run the same **26 tests/90 assertions**, retaining committed PostgreSQL and locking semantics. All six trials passed with no skips/errors. Same host/runtime, one worker, fresh process and initially empty database per repetition, durability enabled, warm dependency/source filesystem. Interleaved order: migrations, truncation, truncation, migrations, migrations, truncation.

| Strategy | Wall trials (seconds) | Median wall | Median child user+systemCPU |
| --- | --- | ---: | ---: |
| Per-case migrations | 37.57,37.51,39.05 | 37.57s | 8.27s |
| Schema reuse/reference restoration | 14.70,15.41,13.96 | 14.70s | 2.14s |

**Class median wall reduction:60.9%, or22.87 seconds.** No additional worker produced this improvement. ChildCPU excludes PostgreSQL serverCPU and is not total infrastructure compute. This isolates reset strategy, not owner selection or all earlier bootstrap changes.

Historical full-suite success at **5760d3e4c01ceb5ec6ddec46c170ca80e952f345** was **575.23s/1,482 cases**. Current local default-order execution is **313.50s/1,507 cases**. Exact hardware/cache/workload equivalence is not established, so no causal whole-suite percentage is claimed.

A full-suite counterfactual restoring per-case migrations failed two intentional architecture guards rejecting those resets outside Schema. It is an **ineligible performance control**, not a passing baseline or current-branch regression. The guards were not weakened or excluded. A matched whole-application before/after percentage remains unestablished.

## Failure paths, budgets and limits

All eighteen reporter cases passed. Actual CLI checks returned0 for successful results,1 for failed results,2 for missing input/invalid limits. Synthetic (runner,writer) statuses (0,0),(0,74),(42,0),(42,74) returned0,74,42,42. These verify failure handling, not business behavior.

Local investigation references are approximately **two seconds for fast PHP** and **five to six minutes for complete two-worker execution**. They are not portable timeouts or relaxed thresholds. Keep existing CI timeouts until repeated comparable hosted evidence supports budgets. Record revision, versions, workers, counts/skips, cache conditions and repetition. Per-case setup/cleanup is not separately instrumented by JUnit; the controlled experiment isolates reset strategy and CI exposes setup/quality phases.

Automatic changed-code selection and extra workers remain deliberately unintroduced. Explicit owner commands, conservative dependency fallback, download caches, cancellation, disjoint discovery, early guards and timing/outcome artifacts are implemented. No required security, acceptance, integration or deployment gate became a non-blocking scheduled check. Final normal CI must verify the containing revision and be recorded in PR checks; focused browser success is not its replacement. PR #163 remains draft and unmerged.
