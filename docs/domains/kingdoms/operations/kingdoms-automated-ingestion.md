# Kingdoms automated ingestion operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-004` through `K4-P5` / Slice E  
**Current delivery:** Generic control plane, delegated player/game-Alliance promotion, scheduled acquisition/cursor/retry/replay, source-revocation reconciliation, bounded operational retention and health monitoring; no concrete production source

## Runtime ownership

K4 remains first-party Laravel/PostgreSQL under `app/Domain/Kingdoms` and uses shared Laravel Scheduler/Redis/Horizon infrastructure. Production `config/kingdoms.php` intentionally contains an empty `ingestion_adapters` allowlist, so normal production state has no real external source to poll.

## First-party surfaces

The manager surface is `/alliance/kingdom-ingestion/manage` with `kingdoms.manage`; human mutations including replay require recent password confirmation. There is no public source endpoint, inbound webhook, tenant URL/credential form, arbitrary staging endpoint or public promotion API.

Operator commands are:

- `kingdoms:queue-ingestion --limit=100` — queue due approved subscriptions;
- `kingdoms:reconcile-ingestion-sources --limit=500` — disable subscriptions whose adapter key/version is no longer approved;
- `kingdoms:enforce-ingestion-retention` — redact/prune age-qualified operational scaffolding; and
- `kingdoms:ingestion-health --json` — emit bounded health signals and return non-zero when attention is required.

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

## Source revocation

`kingdoms:reconcile-ingestion-sources --limit=1000` runs every five minutes with `onOneServer()` and `withoutOverlapping(10)`.

For each active/paused subscription it re-resolves the current adapter registry under a row lock. If the adapter key is missing or its version differs from the captured version, the subscription is changed to `disabled`, bounded `source_unapproved` block/failure state is recorded, and future scheduling/circuit state is cleared.

Do not directly re-enable a revoked source in the database. Source use resumes only after a reviewed repository/operator adapter approval and normal tenant management.

## Operational health and alerts

`kingdoms:ingestion-health --json` reports aggregate, payload-free counts for active subscriptions, source-revoked subscriptions, overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches plus `attentionRequired`.

Repository-controlled defaults are five minutes overdue, fifteen minutes stale-pending, twenty-five quarantined candidates and a sixty-minute recent-failure window. These are generic operator signals, not source-specific SLOs and not authority to mutate business state.

The health query path is performance-gated at realistic volume: 250 subscriptions, 40 failed batches and 110 candidates must remain within eight SELECT queries.

## Failure, backoff and circuit behavior

- acquisition errors: bounded `acquisition_failed` plus exponential retry state;
- invalid acquisition contract: `source_contract_invalid`;
- processing validation failure: `processing_validation_failed`;
- source adapter unavailable/version unapproved: fail closed with `source_unapproved` or other bounded source code;
- Kingdom drift: block preserved captured context; never retarget;
- after three consecutive acquisition failures, a bounded circuit-open interval prevents immediate re-acquisition;
- queue retry exhaustion finalizes a still-pending batch as `failed/retry_exhausted`;
- completed source-window replay is accepted only when its next cursor matches the stored value; and
- cursor conflict/divergence stops rather than rewinding or guessing.

## Retention and pruning

`kingdoms:enforce-ingestion-retention` runs daily at 04:15 with `onOneServer()` and `withoutOverlapping(60)`.

Default repository-controlled windows are:

- promoted/rejected normalized candidate payload: redact after 30 days;
- promoted/rejected candidate row: purge after 90 days;
- quarantined candidate row: purge after 180 days;
- completed/partial/failed/blocked batch: purge after 90 days only when no candidates remain; and
- disabled subscription scheduling/failure/circuit fields: compact after 30 days while preserving the subscription row.

Pruning never deletes promoted K1/K3 snapshots/observations or rewrites their machine provenance. Operational retention is not a replay mechanism.

## Recovery and reconciliation

Safe recovery begins by restoring runtime/database/Redis health, inspecting `kingdoms:ingestion-health --json`, then reconciling current source approval before acquisition resumes. Pause/disable subscriptions when uncertainty remains.

For quarantined candidates whose legitimate target relationship has since been corrected by a human workflow, use password-confirmed manager replay. Never directly reset promoted/rejected candidates, rewrite source identities or bypass the promotion actions.

After restore, validate representative source cursor/next-run/circuit state, batch/candidate correlation and independence of canonical promoted history from operational K4 retention.

## Diagnostics and privacy

Use safe Alliance/Kingdom/subscription/batch/candidate/promoted-record IDs, adapter key/version, source-window/record IDs, opaque cursor, capture time, hashes, next-run/claim/success/failure/circuit timing, state/reason codes and internal audit/outbox IDs.

Never persist/log normalized payload bodies as diagnostics, raw source responses, endpoint/credential/header/cookie material, private diplomacy/contact text or raw exception text.

## Capacity and stop conditions

The generic contract caps one acquisition page at 250 normalized records and one adapter poll interval at 60–86,400 seconds. Queue concurrency and the operational aggregate-query set are deliberately bounded. These are safety/capacity gates, not a proven real-source throughput SLO.

Stop/escalate if operation would require unapproved source/network/credentials, raw-response archiving, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact mutation, scoring/recommendation, or changing queue/cursor/candidate state by hand.

## Acceptance evidence

K4-P5 runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed Dependency Review `31552113152`, CodeQL `31552113044`, and CI `31552113042`: Pint 528 files, PHPStan 374/374 zero errors, 428 tests / 9,736 assertions, frontend/build, migrations, immutable image, staging, backup/restore and scan.

See [Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md) and [Slice E security/privacy review](../security/kingdoms-automated-ingestion-operations-security-review.md).
