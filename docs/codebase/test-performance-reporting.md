# Test performance reporting

Status: Implemented and runtime-verified on 2026-09-10. The [validation report](test-validation-2026-09-10.md) records the immutable revisions, full CI results and measurement conditions. Testing-system verification does not complete the other work in PR #163 or authorize merging it.

[Testing](testing.md) owns commands and isolation; [ADR-0043](../architecture/adr/0043-owner-first-tests-and-disjoint-execution-suites.md) owns the folder decision.

## Inspect results without rerunning tests

With PHP 8.5 and DOM/XML enabled, from the repository root:

```sh
php scripts/summarize-test-timings.php /path/to/existing-junit.xml --limit=15
```

The command reads an existing local XML file and canonical execution-type names from `scripts/test-layout.php`. It does not load PHPUnit, providers or Laravel. Its implementation and regression cases live in `tests/Shared/Testing/Support` and `tests/Shared/Testing/Unit`. All eighteen reporter cases have passed; actual Composer filter/report forwarding was also exercised in the hosted environment.

The reporter counts testcase records once instead of summing nested suite totals. It preserves failure/error/skip markers, declared totals, unknown durations, execution-type totals, slow files, provider labels and input SHA-256. Outcome markers stay beside file/type timings so early failure is not mistaken for inexpensive successful execution. Missing or ambiguous paths remain unclassified. Missing duration is not a measured zero. Suite-only skips are not assigned invented case or file identities.

| Exit | Meaning |
| --- | --- |
| 0 | Report processed without recorded failure/error or detected counter mismatch. This alone does not prove full discovery, zero skips or complete regression. |
| 1 | Recorded or declared failures/errors; the summary is still emitted. |
| 2 | Missing, unreadable, empty, malformed, unsafe or inconsistent input, invalid options or failed output. Parseable mismatches still emit diagnostics. |

Unknown totals remain unknown. XML document/entity declarations are rejected and network loading is disabled. Report names are escaped in Markdown; stack traces are not copied into summaries. The original XML remains the detailed diagnostic source. A matching case count does not excuse declared counters below observed markers. Case-count mismatch takes exit 2; otherwise failures/errors take exit 1 before remaining counter contradictions take exit 2.

## CI phases and artifacts

Main CI executes layout, Pint, PHPStan and complete regression once each in that order. A source guard compares this sequence with `composer check:ci` so new aggregate checks cannot silently disappear from CI. The dependency-free layout guard runs after PHP setup, before dependency installation and explicit fresh-database migration. Service-container startup still precedes the job steps.

The `phpunit-results` artifact retains these files under `storage/logs` when their phases are reached:

| File | Evidence |
| --- | --- |
| `phpunit-junit.xml` | Unmodified runner results. |
| `phpunit-timings.md` | Ranked outcomes and aggregate case durations, also posted to the job summary. |
| `phpunit-process-time.txt` | GNU time wall, user/system CPU, maximum resident memory and child exit status. |
| `phpunit-environment.txt` | Actual checkout revision, run/attempt, runner platform, timestamp, installed versions and exact test script. |
| `phpunit-report.log` | Reporter input/output diagnostics. |

The timer wraps `composer test:ci`, including its preflight/config-clear overhead. It does not separately measure each method's fixtures or cleanup. Dependency installation, initial migration, formatting and static analysis remain visible phases. Never subtract parallel aggregate case duration from wrapper wall time to estimate setup costs.

The revision comes from `git rev-parse HEAD`; a PR checkout can be a synthetic merge commit rather than the head SHA. This is GitHub's test checkout, not an authorization to merge the PR. Environment reporting does not dump secrets. The existing two PHP workers, browser configuration, security and database durability checks, downstream deployment/recovery and cancellation policy remain intact.

Test and report failures remain blocking. Reporting cannot turn an earlier failed test step green, and artifacts are retained on failure. Intelligence's runner-to-tee wrapper captures both pipeline statuses immediately: retain the runner's failure first, otherwise retain a diagnostic-writer failure. Executed checks of (runner, writer) values (0, 0), (0, 74), (42, 0), (42, 74) returned 0, 74, 42, 42. Missing results after an attempted regression and unexpectedly empty selections fail.

## Measurement interpretation

The [validation report](test-validation-2026-09-10.md) is the canonical home for trial data, workflow IDs and final results. The controlled reset-strategy comparison used the same 26 cases and 90 assertions at one worker, with all six trials passing. Median class wall time changed from 37.57 seconds with per-case migrations to 14.70 seconds with schema reuse and reference restoration: a 60.9% improvement for that class, not the whole application. Child CPU excludes the database server's CPU.

Complete local default/random-order PHP runs passed 1,507 cases and 83,068 assertions in 313.50 and 315.11 seconds. Fast PHP views passed 209 cases in 1.82 seconds process wall time. These are environment-specific investigation references, not portable timeouts or relaxed acceptance thresholds.

Final normal CI at PR head `e6f29ebbd40687c38a43554facb3ec9ef872ea8c` checked out `4485b87f0ab5aef0f93d2f70f8ae6b088697c22b` and passed the full PHP inventory. Artifact `10153732965` records 907.92 seconds wrapper wall time with two workers. This is not comparable directly to the local timings. Integration remains the largest recorded aggregate category; use its slow-file diagnostics to guide a future measured change, not to justify discarding real locking or committed-state verification.

The [historical baseline](test-performance-baseline-2026-09-09.md) was 575.23 seconds for 1,482 cases. Hardware/cache/workload equivalence with current runs is unestablished. A full-suite control restoring per-case migrations failed intentional architecture guards and is not a valid successful performance control. The class-level experiment must not be presented as a causal full-suite percentage.

Future comparisons must record immutable source, exact commands, installed versions, hardware, worker count, failures/skips, cold/warm cache conditions and repetitions. A dependency-cache hit does not establish a warm database. Separate cheaper tests from selecting fewer tests and from adding workers. Keep existing CI timeouts until repeated comparable hosted data supports a new budget. Coverage percentages were not measured and no threshold was reduced.
