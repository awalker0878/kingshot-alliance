# PR 163: measured test-performance baseline

This is an incremental evidence checkpoint, not a declaration that PR 163 is complete.

## Baseline and environment

The immutable source checkout was `5760d3e4c01ceb5ec6ddec46c170ca80e952f345`, with the Intelligence workflow-only change subsequently published as `be01eb738d4f101ab65425f8593a7b2ae862febc`. The full PHP baseline ran from a separate source/dependency copy, not the directory being reorganized.

Local environment: PHP 8.5.10, PHPUnit 12.5.33, ParaTest 7.20.0, PostgreSQL 18.6, two test workers, loopback PostgreSQL on a dedicated ephemeral port. PostgreSQL `fsync`, `synchronous_commit`, and `full_page_writes` remained enabled. No transaction-isolation traits, assertions, test methods, data providers, or snapshot tolerances were changed for this measurement.

```sh
php artisan test --parallel --processes=2 --log-junit /path/to/baseline-junit.xml
```

The command exited **0**: **1,482 tests passed, 82,984 assertions**. Runner duration was **574.67 seconds**; `/usr/bin/time -v` measured **9:35.23 wall time** and **173,756 KiB maximum resident set size**. The JUnit sum of test durations was 1,131.400 seconds; that is aggregate worker time, not wall time.

The existing architecture-only lane separately passed **63 tests / 69,609 assertions** in approximately **1.53 seconds**. This demonstrates why the architecture gate should not rerun the entire database-backed application suite.

These are local measurements, not GitHub-hosted runner timings. They do not establish a speedup until a comparable optimized run is recorded. Locked dependencies were recovered from the PR checkout-evidence artifact; dependency installation itself was not part of the measurement.

## Initial bottleneck evidence

The largest class totals in the full baseline were:

| Original class basename | Cases | Aggregate seconds |
| --- | ---: | ---: |
| FinalizedAccountMutationV3Test | 26 | 47.36 |
| AccountLoginCompletionV3Test | 26 | 46.48 |
| DurablePasswordResetIssuanceV3Test | 19 | 41.50 |
| ProtectedMembershipTargetConcurrencyV3Test | 20 | 40.81 |
| RecruitmentRetentionAuthorityV3Test | 20 | 40.42 |
| TransferCompletionLockOrderV3Test | 17 | 34.67 |
| OwnedPlayerMutationConcurrencyV3Test | 16 | 32.49 |
| InvitationAdmissionConcurrencyV3Test | 17 | 32.46 |
| RememberedAccountSessionV3Test | 16 | 30.82 |
| StablePlayerIdentityConcurrencyV3Test | 15 | 30.06 |

Inspect committed-state database setup before changing application semantics. Concurrency tests must retain real PostgreSQL connections, lock behavior, after-commit visibility, and durable state. Replacing their isolation with a surrounding rollback transaction solely to improve timing would invalidate their contracts.

## Publication and remaining verification

- Published checkpoint `be01eb73` replaces the Intelligence workflow's duplicate complete serial suite with its own 39 context/read-model classes, preserving PostgreSQL execution, failure propagation, and always-retained JUnit diagnostics. The full application CI command remains authoritative.
- The standard-folder migration must preserve all 1,482 existing PHP identities, including data-provider cases, and all 62 browser identities. Any added test must be declared separately rather than masking a removed case with a matching total.
- Validate the actual final migration tree: discovery, namespaces, imports, fixture paths, workflow selections, formatting, source guards, and full containing execution. A prepared tree or a passing source-only gate is not equivalent to a full-suite pass.
- Record optimized measurements with the same worker count and environment; report wall time and aggregate test time separately.
- Existing visual-regression failures require their own diagnosis. Do not delete tests, relax pixel or size tolerances, or blindly regenerate snapshots to obtain a green status.
