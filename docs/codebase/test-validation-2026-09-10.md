# Testing-system runtime validation — 2026-09-10

Status: Testing-system implementation and its final required verification are complete at the revision below. Other application-hardening work in PR #163 remains incomplete. Keep the PR draft and unmerged; these results are not a release or merge approval.

The agreed structure remains [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md). [Testing](testing.md), [Browser testing](browser-testing.md) and [performance reporting](test-performance-reporting.md) own operating instructions. This report is the canonical record of executed verification and measured performance, including their limitations.

## Final normal CI confirmation

Verified PR head: **`e6f29ebbd40687c38a43554facb3ec9ef872ea8c`**. GitHub's actual synthetic merge checkout: **`4485b87f0ab5aef0f93d2f70f8ae6b088697c22b`**, against main **`7e780521295e868005ecfee5bd38b33e8215ec49`**. The synthetic checkout is test infrastructure; the PR was not merged. All nine normal workflows completed successfully on their first run attempt:

| Workflow | Run | Result |
| --- | --- | --- |
| CI | [34479708547](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708547) | Complete PHP, frontend quality/build, container/staging/recovery passed. |
| Visual Regression | [34479708461](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708461) | All 62 cases passed with one worker; Playwright reported 5.6 minutes. |
| Architecture V3 Verification | [34479708446](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708446) | Passed. |
| King Perks Verification | [34479708523](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708523) | Passed. |
| Dependency Review | [34479708411](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708411) | Passed. |
| KingdomMaps Assurance | [34479708497](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708497) | Passed. |
| CodeQL | [34479708425](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708425) | Passed. |
| Intelligence Verification | [34479708408](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708408) | Passed. |
| Gift Code Verification | [34479708453](https://github.com/awalker0878/kingshot-alliance/actions/runs/34479708453) | Passed. |

Backend job `102879121644` passed Composer validation/audit, fresh durable PostgreSQL installation, layout verification, Pint, PHPStan and complete regression. Frontend job `102879122033` passed dependency audit and the full check/build sequence, including Node source contracts. Container job `102885030290` passed configuration validation, production image build, staging deployment, backup/restore and image scanning. The visual job `102879121972` lists each of the 62 cases once, with no retry, flaky or skipped test outcome. Failure-only diagnostic steps were skipped because the jobs succeeded; they are not skipped test cases.

Downloaded artifact **`10153732965`**, `phpunit-results`, was independently parsed rather than relying on the workflow badge. It contains **1,507 unique PHP case identities and 83,068 assertions, with zero failures, errors or skips**. All case durations are supplied. The 18 reporter cases and their 51 assertions are included. Declared counters match the case records, the reporter log is empty and the process exit status is 0.

| Final PHP measurement | Value |
| --- | ---: |
| Full `composer test:ci` wrapper wall time, two workers | 907.92 seconds |
| Child user / system CPU | 141.20 / 39.94 seconds |
| Maximum resident memory | 234,508 KiB |
| Aggregate case duration | 1,781.654801 seconds |
| Integration | 512 cases / 1,565.607382 aggregate seconds |
| Feature | 786 cases / 214.454071 aggregate seconds |
| Architecture | 74 cases / 1.491873 aggregate seconds |
| Unit | 125 cases / 0.098291 aggregate seconds |
| Frontend | 10 cases / 0.003184 aggregate seconds |

JUnit SHA-256: `542451055014a36d3564bee1715cb0d81c4ee148932cb019027823e284cde05d`. Downloaded artifact ZIP digest: `96864b27a4ba29d32be0dd6d775a3805601ba578aaf1c3f58de0c97c2224b9d4`. Raw XML, process timing, installed versions and the actual checkout are retained together. Artifact retention is finite; this document retains the identifiers and measurements without duplicating the raw test inventory.

This final normal browser result supersedes the earlier 60-of-62 failure and the focused four-case verification as the full-suite acceptance result. Later documentation-only corrections do not alter runtime source, tests, configuration or dependencies; they were not used to relabel this run as testing a different SHA. No redundant full run is needed merely to record its result.

## Environment and source

Validation started at `f49a8332b953e924193d2aa3e7b09cb127682a57`. A read-only export at `c3de8d2e` reconciled 2,950 tracked blobs, including export-ignored tests. Fixes were committed through the connector without force updates. The temporary source, copy-preparation and focused validation workflows were removed before the final normal run; the permanent gates remain.

Installed local versions were PHP 8.5.10, Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0, Pint 1.30.4, Node 24.20.0, TypeScript 5.9.3, Playwright 1.62.1 and PostgreSQL 18.6. Installed metadata matched the lockfile. Final hosted metadata confirms PHP 8.5.10, Composer 2.10.3, Laravel 13.30.1, PHPUnit 12.5.33, ParaTest 7.20.0 and PostgreSQL 18.6; browser logs confirm Node 24.20.0 and npm 11.19.0 on Ubuntu 24.04.5. Coverage instrumentation was disabled in the ordinary verification runs.

Local PostgreSQL used an isolated loopback instance with separate test/worker databases. fsync, synchronous_commit and full_page_writes stayed enabled; max_locks_per_transaction was 256. Final CI's database durability checks also passed. The host blocked local Chromium navigation by policy, so browser verification used the approved hosted environment instead of bypassing that restriction.

## Runtime defects corrected

| Commit | Correction and preserved boundary |
| --- | --- |
| `70706069` | Six isolation setup errors exposed an incorrect assumption that event_metric_definitions contained migration rows. Snapshot only the five genuinely populated tables; metrics still undergo truncation and dirty-baseline rejection. Add a real insert/reject/reset regression. |
| `81e90a67` | Replace nonexistent command/brief URLs with actual dashboard regions, assert navigation succeeds and await link destinations. Retain all seven acceptance surfaces. |
| `8bd15063` | Supply the actual Inertia asset version for five AJAX requests after built assets exposed four HTTP 409 failures. Keep middleware, authorization, status, content and telemetry assertions. |
| `e21c4f41` | Connect fourteen erroneous candidate translation references to existing catalogue keys without changing forms, actions or authorization. Add an owner-local source regression. |
| `75f4b709` | Replace broad DOM mutation with normalization of concrete IDs and validated formatted timestamps. Preserve surrounding labels, amounts, levels, counts and relative-age/freshness text; add raw semantic assertions and ten source cases. |
| `74d14b34` | Review all fourteen screenshots/text captures before replacing obsolete fingerprints for the same seven surfaces. Capture screenshots on mismatch, retain raw/normalized text and preserve all twelve PNG baselines. |

The translation correction fixes an observed UI defect, not production behavior to accelerate tests. The [acceptance review](acceptance-baseline-review-2026-09-10.md) records the protected facts and rendered review.

## Additional executed checks

These earlier measurements complement, but do not replace, the final normal run above.

| Check | Conditions | Result |
| --- | --- | --- |
| Fast PHP | Unit + Architecture + Frontend; one worker | 209 tests / 71,983 assertions; 1.450 seconds runner, 1.82 seconds process wall; no failures or skips. |
| Reference/cache isolation | `70706069`; durable PostgreSQL; one worker | 10 tests / 44 assertions; 5.832 seconds; no failures or skips. |
| Affected owners | `70706069`; randomized seed 163 | 129 tests / 3,744 assertions; 13.287 seconds; no failures or skips. |
| Mixed reset/concurrency | `70706069`; two workers, seed 164 | 37 tests / 147 assertions; 19.552 seconds; no failures or skips. |
| Rules/debrief AJAX | `8bd15063`; asset manifest present, then absent | Both pass 5 tests / 18 assertions; 2.669 and 2.230 seconds. |
| Complete local PHP | `8bd15063` PHP source; two workers, fresh databases/process, built assets, warm dependency/source filesystem | 1,507 tests / 83,068 assertions; 313.50 seconds wall; no failures, errors or skips. |
| Complete local PHP, randomized | Same PHP source; two workers, seed 20260910, separate fresh databases, built assets | 1,507 tests / 83,068 assertions; 315.11 seconds wall; no failures, errors or skips. |
| Node source contracts | `75f4b709`, unchanged at `74d14b34`; Node 24 | Eleven passing cases in two files; final local repeat approximately 112 milliseconds. |
| Repeated browser acceptance | `74d14b34`; run 34476453647; both viewports twice, retries=0 | Four passes; no flaky, skipped or failing cases; 60.747 seconds. |
| Actual Composer forwarding | Same hosted focused run; Composer 2.10.3 | Filter/report arguments reach PHPUnit; 18 tests / 51 assertions pass; unexpectedly empty filter exits 1. |

Subsequent browser/Node/copy changes through `74d14b34` did not modify PHP implementation or test bodies after `8bd15063`. Randomized success applies to the recorded seeds and workers, not every possible schedule. Local times exclude dependency installation.

Earlier normal CI `34469136878`, head `70706069`, merge checkout `ad36e713a65a6cc498e3f03e129c1b6f442ffe13`, also passed full PHP and source-quality/deployment gates. Its artifact `10149366260` recorded 901.56 seconds wrapper wall time and 1,768.817741 aggregate case seconds. Its visual run passed only 60 of 62 cases and prompted the subsequent repair. These earlier partial visual results are not the final acceptance evidence.

## Discovery reconciliation

Actual pre-owner discovery at `b640ee571e3f9a5e4074c91bb67064b0b7623afa` found 1,488 expanded PHP identities. Explicit class/namespace/path mappings retain every one, including provider labels. Current 1,507 equals those 1,488 plus 18 reporter cases and one metric-reset case. None disappeared or was combined. Final XML independently contains 1,507 unique identities.

| PHP view | Files | Expanded cases |
| --- | ---: | ---: |
| Unit | 18 | 125 |
| Feature | 190 | 786 |
| Integration | 53 | 512 |
| Architecture | 28 | 74 |
| Frontend | 3 | 10 |
| **Total** | **292** | **1,507** |

Each file is assigned once. Browser reconciliation preserves 62 title/project identities in 17 specs and all 12 PNG baselines. Eleven Node cases form a separate mandatory frontend path. The older performance baseline predates six pre-owner cases and records 1,482, so it is not the discovery baseline. Identity and assertion counts are not line-coverage percentages; no coverage threshold was reduced.

## Controlled reset-strategy comparison

Base source: `70706069`. Class: `tests/Contexts/Accounts/Identity/Integration/Concurrency/FinalizedAccountMutationV3Test.php`. Source SHA-256: `3ba7a876e312547453c28d7106dcc7e7237f4461e11c2b9e3527622f13d01908`.

The separate control changes only the DatabaseTruncation import/use to DatabaseMigrations. Both arms execute the same 26 tests and 90 assertions, retaining committed PostgreSQL and locking semantics. All six trials passed without errors or skips. Conditions: same host/runtime, one worker, fresh process and initially empty database per repetition, durability enabled, warm dependency/source filesystem. Interleaved order: migrations, truncation, truncation, migrations, migrations, truncation.

| Strategy | Wall trials, seconds | Median wall | Median child user + system CPU |
| --- | --- | ---: | ---: |
| Per-case migrations | 37.57, 37.51, 39.05 | 37.57 seconds | 8.27 seconds |
| Schema reuse/reference restoration | 14.70, 15.41, 13.96 | 14.70 seconds | 2.14 seconds |

Class median wall reduction: **60.9%, or 22.87 seconds**. No additional worker produced this improvement. Child CPU excludes database-server CPU and is not total infrastructure compute. This isolates reset strategy, not owner selection or all prior bootstrap changes.

Historical full-suite success at `5760d3e4c01ceb5ec6ddec46c170ca80e952f345` was 575.23 seconds for 1,482 cases. Current local default-order execution is 313.50 seconds for 1,507 cases. Exact hardware/cache/workload equivalence is not established, so no causal whole-suite percentage is claimed. The final hosted 907.92 seconds is also a different environment, not a local before/after comparison.

A full-suite counterfactual restoring per-case migrations failed two intentional architecture guards rejecting those resets outside Schema. It is an ineligible performance control, not a passing baseline or a current-branch regression. The guards were not weakened or excluded. A matched whole-application improvement percentage remains unestablished.

## Failure paths, budgets and remaining limits

All eighteen reporter cases passed. Actual CLI checks returned 0 for successful results, 1 for failed results and 2 for missing input/invalid limits. Synthetic (runner, writer) values (0, 0), (0, 74), (42, 0), (42, 74) returned 0, 74, 42, 42. These verify failure handling, not business behavior.

Local investigation references are roughly two seconds for fast PHP and five to six minutes for full two-worker execution. They are not portable timeouts or relaxed thresholds. Preserve existing CI timeouts until repeated comparable hosted evidence supports new budgets. Record source, versions, workers, failures/skips, cache conditions and repetitions. JUnit does not separately instrument per-case fixture/setup/cleanup costs; the controlled experiment isolates reset strategy and CI exposes preparation/quality phases.

Automatic changed-code selection and extra workers remain deliberately unintroduced. Explicit owner commands, conservative dependency fallback, safe download caches, cancellation, disjoint discovery, early guards and timing/outcome artifacts are implemented. No required security, acceptance, integration or deployment gate became a non-blocking scheduled check.

There is no remaining failed testing-system gate at the verified revision. Future tests, fixtures, dependencies or worker changes still require their appropriate verification. Completion here does not resolve the other product/architecture work in PR #163, measure a line-coverage percentage, establish a causal whole-suite speed-up, or authorize release or merging. The PR stays draft and unmerged.
