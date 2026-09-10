# Test performance reporting

Status: Source implementation and reconciliation complete; test execution and hosted CI acceptance remain on hold.

This guide owns timing interpretation and result-reporting diagnostics. [Testing](testing.md) owns execution commands, isolation and the current inventory; [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) owns the folder decision.

## Inspect existing results without rerunning tests

From the repository root, with PHP 8.5 and DOM/XML enabled:

```sh
php scripts/summarize-test-timings.php /path/to/existing-junit.xml --limit=15
```

The command reads a local XML file and canonical execution-type names from `scripts/test-layout.php`. It does not load PHPUnit, test classes, providers, Composer's application autoloader or Laravel. Its implementation lives in `tests/Shared/Testing/Support/JUnitTimingReport.php`; its authored regression cases live beside it in `Shared/Testing/Unit`.

It counts testcase records once, not nested suite totals repeatedly. Output includes recorded failures/errors/skips, declared totals, missing timings, type totals, slow files, provider-labelled case names and the input SHA-256. Type and file rows include their observed failure/error/skip markers, so an early failure is not mistaken for a cheap successful test. Suite-only skips are not assigned to a file or type without case identities. Type grouping recognizes owner-local and historical tier-first paths. Missing or ambiguous paths remain explicitly unclassified. Missing duration is not a measured zero. Entire skipped classes can have suite-level counts without individual case identities; those remain distinct from observed testcase records and unexplained count mismatches.

| Exit | Meaning |
| --- | --- |
| 0 | A report was processed with no recorded failure/error, case-count mismatch or declared counter below observed markers. This does not certify full discovery, zero skips or complete regression. |
| 1 | The input reports failures or errors, including declared counters without detailed markers. The summary is still emitted. |
| 2 | Input is missing, unreadable, empty, malformed, unsafe or inconsistent, or summary output fails. Parseable count mismatches and contradictory skipped totals still emit diagnostics. |

Unknown totals are labelled unknown rather than invented. Empty case inventories fail. XML document/entity declarations are rejected and network loading is disabled. Report names are escaped before Markdown output; failure messages and stack traces are not copied into the summary. The original XML remains the detailed diagnostic source. Case-count reconciliation and declared-counter contradictions are reported separately: matching the number of cases does not excuse a declared zero skips when case records contain skips. A case-count mismatch takes exit 2; otherwise recorded failures/errors take exit 1 before remaining counter contradictions take exit 2.

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

Source parent: `7eb905f63bda2ec2bb491206d5faacd5d747a8d9`. Initial reporting checkpoint: `88c9e66794745f829a2e7c4eae00f7898a10c2d6`. Hardened reporter checkpoint: `36fa08fcdd1cbe3483005ead731ed8b165f174ba`.

`3c10cb0c` moves the unchanged notification registration method from the database-backed delivery class into the workflow owner's Architecture directory. It retains actual Artisan registration, all six assertions, the five other delivery scenarios and their database/clock cleanup. The registration check still boots Laravel but no longer requests database reset. The original six methods are present exactly once.

`d4f00b36` adds the report library, CLI and seven test methods with ten invalid-input provider rows: sixteen authored cases, not executed cases. They address nested counts, marker preservation, unknown timing/types, skipped classes, declared errors, ranking, escaping and invalid input. `88c9e667` connects result reporting and phase timing to CI without another full-suite invocation. `36fa08fc` adds grouped outcome markers and rejects contradictory skipped totals; two additional regression methods bring the reporter to eighteen authored cases. Removing those two methods exactly reproduces the previous test source, including every assertion and provider row. None of these cases have executed during the hold.

The CLI was used only to process already-completed run `34387606165`, revision `9951bcbbec4e5c1416ec80f9cae15f2f82572bd1`, artifact `10118771307`. That old XML contains 1,482 case records, 645 error markers, two failure markers and no skips; both reporter versions returned exit 1. Independent XML counting agreed. Known aggregate case duration is 967.723913 seconds, not wall time and not a new timing experiment. Input SHA-256: `86b23fd24560a5ca59ab56f112c6ddc1d1ab9ba2ec670b8736b0accd7c2dade9`. This earlier failed database-reset run is failure-reporting evidence only, not validation of the later repair or a speedup.

At `36fa08fc`, the independently reconstructed complete tests subtree matches GitHub tree `db48cff6b36796956adc9758b6e8370a53d0c255`; the scripts subtree matches `fd0d00ef832fdc3e4239bb714c07ca5447a6fb73`. Source-only synchronization/layout checks assign 290 PHP test files exactly once: 17 Unit, 189 Feature, 53 Integration, 28 Architecture and 3 Frontend. All 321 PHP files under tests and scripts pass PHP 8.5.10 syntax and Pint 1.30.4 formatting checks. Recovered installed versions match composer.lock; they include Laravel 13.30.1, PHPUnit 12.5.33 and ParaTest 7.20.0. TypeScript 5.9.3 parsed all 17 browser specifications plus the unchanged Playwright configuration without evaluating them. Workflow YAML and 73 run blocks passed parsing/Bash syntax checks without execution. The CI file hash matches `e9310e16c9e9273875fe71c5979da40bfea9812d`; concurrent CI/documentation edits were preserved rather than overwritten or force-updated.

## Runtime acceptance handoff

The source-only implementation phase is closed out, not the full optimization acceptance. No runtime check is marked passing. Keep the PR draft and unmerged until authorized verification is recorded against an immutable final revision.

First verify the earlier reference-reset repair in `tests/Shared/Testing/Integration`, including mixed DatabaseTruncation/RefreshDatabase order, restored migration rows and sequences, dirty-state rejection and teardown failures. A passing reporter or source guard cannot validate database isolation.

Then verify the reporter's eighteen authored cases, CLI failures and Composer argument forwarding in the actual prepared runtime. Reconcile full PHPUnit/provider identities and both Playwright projects, including moved fixture imports and unchanged snapshots. Finally run the required complete PHP, frontend, browser, security and deployment verification and collect comparable cold/warm measurements at the same worker count. Do not silently substitute changed-file selection or a narrow owner run for these gates. Broader caches, reset-strategy changes and increased workers remain deferred until measured isolation evidence supports them.

No test runner, runner discovery, provider, application bootstrap, seeder, migration, browser journey, benchmark or CI dispatch was executed in the source-only closeout. Source checks and processing an old XML report are not current behavioral or performance certification.
