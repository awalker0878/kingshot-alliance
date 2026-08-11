# Kingdoms automated ingestion operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-004` through `K4-P4` / Slice D  
**Current delivery:** Generic control plane, delegated player/game-Alliance promotion, generic scheduled acquisition/cursor/retry/replay; no concrete production source

## Runtime ownership

K4 remains first-party Laravel/PostgreSQL under `app/Domain/Kingdoms` and uses shared Laravel Scheduler/Redis/Horizon infrastructure. Production `config/kingdoms.php` intentionally contains an empty `ingestion_adapters` allowlist, so normal production state has no real external source to poll.

## First-party surfaces

The manager surface is `/alliance/kingdom-ingestion/manage` with `kingdoms.manage`; human mutations including replay require recent password confirmation. There is no public source endpoint, inbound webhook, tenant URL/credential form, arbitrary staging endpoint or public promotion API.

The operator command `kingdoms:queue-ingestion --limit=100` is scheduled every minute using `onOneServer()` and `withoutOverlapping(10)`.

## Durable state

Operators may inspect subscriptions, batches, candidates, canonical player snapshots/game-Alliance observations, Audit and internal outbox evidence.

Subscriptions store opaque source cursor, next-run/claim/success/failure/circuit timing and bounded failure codes. Batches store source-window identity plus the next cursor associated with the window. Candidate payload remains bounded normalized operational data; promoted canonical history copies bounded provenance and is not FK-dependent on K4 operational rows.

Do not rewrite captured Kingdom IDs, adapter versions, cursors, source-window IDs, stable IDs, payload/identity hashes or promoted-record references to force recovery.

## Scheduled acquisition flow

1. Laravel Scheduler runs `kingdoms:queue-ingestion` every minute.
2. Due active subscriptions with no open circuit are selected and rechecked under row lock.
3. A valid acquisition-capable adapter/version is required. The claim stores `last_claimed_at` and advances `next_run_at` before job dispatch.
4. `RunKingdomIngestionSubscriptionJob` runs on dedicated `kingdoms-ingestion` Horizon queue.
5. The adapter receives the current opaque cursor plus a maximum page size of 250 and returns a bounded source-window ID, optional next cursor and records.
6. Existing P1 staging and P2/P3 promotion actions process the page.
7. Completed/Partial batch state is required before cursor advancement.

Jobs are unique/overlap-protected per subscription, timeout at 120 seconds, try at most five times and use 60/300/900/3,600-second queue backoff. Production/staging queue concurrency defaults to 2/1 respectively.

## Promotion and replay

Pending `player_snapshot` candidates require an existing owning-Alliance roster relation. Pending `alliance_observation` candidates require an existing active owning-Alliance tracking relation. Both delegate to accepted business recorders.

Manager replay applies only to quarantined candidates and re-runs the accepted promotion path after rechecking Alliance/current-Kingdom/source version. Replay is not an operator shortcut for guessing identity or creating missing relationships.

## Diagnostics

Use safe Alliance/Kingdom/subscription/batch/candidate/promoted-record IDs, adapter key/version, source-window/record IDs, opaque cursor, capture time, hashes, next-run/claim/success/failure/circuit timing, state/reason codes and internal audit/outbox IDs.

Never persist/log normalized payload bodies, raw source responses, endpoint/credential/header/cookie material, private diplomacy/contact text or raw exception text as scheduler diagnostics.

## Failure, backoff and circuit behavior

- acquisition errors: bounded `acquisition_failed` plus exponential retry state;
- invalid acquisition contract: `source_contract_invalid`;
- processing validation failure: `processing_validation_failed`;
- source adapter unavailable/version unapproved: fail closed with bounded source code;
- Kingdom drift: block preserved captured context; never retarget;
- after three consecutive acquisition failures, a bounded circuit-open interval prevents immediate re-acquisition;
- queue retry exhaustion finalizes a still-pending batch as `failed/retry_exhausted`;
- completed source-window replay is accepted only when its next cursor matches the stored value;
- cursor conflict/divergence stops rather than rewinding or guessing.

## Recovery and reconciliation

Safe recovery begins by restoring runtime/database/Redis health, inspecting bounded subscription/batch state and checking whether the source remains approved. Pause/disable the subscription when uncertainty remains.

For quarantined candidates whose legitimate target relationship has since been corrected by a human workflow, use password-confirmed manager replay. Never directly reset promoted/rejected candidates, rewrite source identities or bypass the promotion actions.

A source adapter removal/version change intentionally blocks future acquisition. Re-enable only through reviewed repository/config approval; P5 will formalize source-revocation procedures.

## Backup, migration and retention

P4 migration `2026_08_11_220000_add_ingestion_scheduling.php` adds scheduling/circuit state and batch next-cursor state. The full Kingdoms migration round-trip test places it after the K4 foundation/provenance chain and drops it first during rollback.

Shared backup/restore applies. After restore, validate representative source cursor/next-run/circuit state, batch/candidate correlation and independence of canonical promoted history from operational K4 retention.

Formal pruning/retention remains K4-P5. Do not manually prune operational rows as a replay strategy.

## Capacity and stop conditions

The generic contract caps one acquisition page at 250 normalized records and one adapter poll interval at 60–86,400 seconds. Queue concurrency is deliberately isolated/bounded. These are safety bounds, not a proven real-source throughput SLO.

Stop/escalate if operation would require unapproved source/network/credentials, raw-response archiving, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact mutation, scoring/recommendation, or changing queue/cursor state by hand.

## Acceptance evidence

K4-P4 runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed Dependency Review `31545866277`, CodeQL `31545866288`, and CI `31545866249`: Pint 523 files, PHPStan 371/371 zero errors, 423 tests / 9,697 assertions, frontend/build, migrations, immutable image, staging, backup/restore and scan.

See [Slice D validation](../product/kingdoms-automated-ingestion-slice-d-validation.md) and [Slice D security review](../security/kingdoms-automated-ingestion-scheduler-security-review.md).
