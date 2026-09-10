# Test performance reporting

Status: Implemented; test execution and hosted CI verification remain pending.

This guide owns timing interpretation and result-reporting diagnostics. [Testing](testing.md) owns execution commands, isolation and the current inventory; [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) owns the folder decision.

## Inspect existing results without rerunning tests

From the repository root, with PHP 8.5 and DOM/XML enabled:

```sh
php scripts/summarize-test-timings.php /path/to/existing-junit.xml --limit=15
```

The command reads a local XML file and canonical execution-type names from `scripts/test-layout.php`. It does not load PHPUnit, test classes, providers, Composer's application autoloader or Laravel. Its implementation lives in `tests/Shared/Testing/Support/JUnitTimingReport.php`; its authored regression cases live beside it in `Shared/Testing/Unit`.

It counts testcase records once, not nested suite totals repeatedly. Output includes recorded failures/errors/skips, declared totals, missing timings, type totals, slow files, provider-labelled case names and the input SHA-256. Type grouping recognizes owner-local and historical tier-first paths. Missing or ambiguous paths remain explicitly unclassified. Missing duration is not a measured zero. Entire skipped classes can have suite-level counts without individual case identities; those remain distinct from observed testcase records and unexplained count mismatches.

| Exit | Meaning |
| --- | --- |
| 0 | A report was processed with no recorded failure/error and no detected case-count mismatch. This does not certify full discovery, zero skips or complete regression. |
| 1 | The input reports failures or errors, including declared counters without detailed markers. The summary is still emitted. |
| 2 | Input is missing, unreadable, empty, malformed, unsafe or inconsistent, or summary output fails. A parseable count mismatch still emits diagnostics. |

Unknown totals are labelled unknown rather than invented. Empty case inventories fail. XML document/entity declarations are rejected and network loading is disabled. Report names are escaped before Markdown output; failure messages and stack traces are not copied into the summary. The original XML remains the detailed diagnostic source.

## CI artifacts and phase boundaries

Main CI executes the same `check:ci` phases exactly once and in the same order: layout, Pint, PHPStan, then `composer test:ci`. Separate steps expose their durations. A source guard compares this sequence to `composer.json` and fails if it changes; update the explicit steps together with the script rather than silently omitting a new check. `composer check:ci` remains available locally.

The existing `phpunit-results` artifact retains these files when their phases are reached:

| File under `storage/logs` | Evidence |
| --- | --- |
| `phpunit-junit.xml` | Unmodified runner result records. |
| `phpunit-timings.md` | Ranked summary of that XML, also published to the job summary. |
| `phpunit-process-time.txt` | GNU time wall, user/system CPU, maximum resident memory and child exit status. |
| `phpunit-environment.txt` | Actual checkout revision, run/attempt, runner platform, timestamp, installed versions and the exact `test:ci` script. |
| `phpunit-report.log` | Reporter input/output errors, if any. |

The timer wraps `composer test:ci`, including its suite freshness/config-clear overhead. It is not a measurement of pure method bodies or isolated fixture setup. Dependency installation, initial fresh-database verification, formatting and static analysis remain separately visible CI steps. Per-test bootstrap, fixture and cleanup time is not independently instrumented by this change. Do not subtract parallel aggregate case time from wrapper wall time to estimate setup cost.

The recorded revision comes from `git rev-parse HEAD`; on a PR this can be the checked-out merge revision, not the PR head. No secret/environment dump is added. Existing two-worker selection, job names, dependency/security checks, PostgreSQL durability checks, downstream staging/recovery, browser gates and cancellation policy remain intact.

The test process remains a blocking step and its exit is not hidden by a reporting pipeline. The summary step runs after an attempted regression unless cancelled; it preserves its own nonzero exit. It cannot turn the earlier failed test step green. Artifacts are still collected on failure. A missing report after an attempted run is a diagnostic failure, not an empty success. No tests run merely because an existing report is summarized.

## Comparable performance evidence

Retain immutable revision, exact commands, versions, worker count, failures/skips, hardware, cache state and repetition count with every comparison. Label cold/warm conditions explicitly; a dependency-cache hit is not proof of a warm database or application. Collect comparable repetitions when authorized. Distinguish reducing test cost, selecting fewer development tests and adding workers. Report CPU/compute trade-offs separately from elapsed time.

The historical successful baseline remains 9:35.23 wall time at two workers; see [its conditions](test-performance-baseline-2026-09-09.md). No comparable successful after-run exists. Directory changes and reporting infrastructure alone establish no speedup or budget. A failing run that omits meaningful work must not be used as an optimization result.

## Source-only continuation evidence

Source parent: `7eb905f63bda2ec2bb491206d5faacd5d747a8d9`. Code checkpoint: `88c9e66794745f829a2e7c4eae00f7898a10c2d6`.

`3c10cb0c` moves the unchanged notification registration method from the database-backed delivery class into the workflow owner's Architecture directory. It retains actual Artisan registration, all six assertions, the five other delivery scenarios and their database/clock cleanup. The registration check still boots Laravel but no longer requests database reset. The original six methods are present exactly once.

`d4f00b36` adds the report library, CLI and seven test methods with ten invalid-input provider rows: sixteen authored cases, not executed cases. They address nested counts, marker preservation, unknown timing/types, skipped classes, declared errors, ranking, escaping and invalid input. `88c9e667` connects result reporting and phase timing to CI without another full-suite invocation.

The CLI was used only to process already-completed run `34387606165`, revision `9951bcbbec4e5c1416ec80f9cae15f2f82572bd1`, artifact `10118771307`. That old XML contains 1,482 case records, 645 error markers, two failure markers and no skips; the reporter returned exit 1. Independent XML counting agreed. Known aggregate case duration is 967.723913 seconds, not wall time and not a new timing experiment. Input SHA-256: `86b23fd24560a5ca59ab56f112c6ddc1d1ab9ba2ec670b8736b0accd7c2dade9`. This earlier failed database-reset run is failure-reporting evidence only, not validation of the later repair or a speedup.

Source checks used actual PHP 8.5.10 and Pint 1.30.4. Recovered installed metadata matches locked Laravel 13.30.1, PHPUnit 12.5.33 and ParaTest 7.20.0. Syntax/formatting, extraction/reinsertion, path/suite checks, XML/file hashes, workflow YAML structure and Bash/inline-PHP syntax were checked. The workflow blocks, report regression cases and test runners were not executed. Hosted timing, CLI invalid-input cases, complete discovery, mixed-order database isolation and required full regression remain pending. The earlier migration-reference reset repair remains the first runtime-validation priority.
