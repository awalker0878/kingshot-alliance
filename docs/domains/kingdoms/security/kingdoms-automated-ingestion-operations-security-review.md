# KINGDOMS-004 Slice E operations security/privacy review

[← Kingdoms security profile](README.md)

**Scope:** `K4-P5` / Slice E — operations, review, retention and source-revocation hardening  
**Status:** Complete  
**Runtime candidate:** `eb706a96c9c875dd41e932e0691e4258f33e01f1`

## 1. Review purpose

Slice E hardens the already accepted K4 ingestion runtime without widening acquisition, identity, tenancy or business-mutation authority. This review covers source approval revocation, operational-data retention, health/alert signals, scheduled maintenance and capacity-query behavior.

It does **not** approve a concrete production source, endpoint, network path or credential lifecycle. Production `ingestion_adapters` remains empty.

## 2. Source-revocation boundary

`ReconcileKingdomIngestionSources` re-evaluates active/paused subscriptions against the current repository/operator adapter registry. If the adapter key is absent or the stored version is no longer the approved version, the subscription is disabled under a row lock with bounded `source_unapproved` state and future scheduling/circuit state is cleared.

Revocation therefore fails closed. It does not silently substitute another adapter/version, rewrite captured Kingdom/source identity, retarget queued work or approve a source because historical rows reference it.

The reconciliation command is bounded and scheduled every five minutes. Restoring source use requires a separately reviewed repository/config approval and normal subscription management; operators do not repair revocation by directly changing database state.

## 3. Retention and canonical-history separation

K4 subscription/batch/candidate rows are operational scaffolding. Promoted K1/K3 `PlayerSnapshot` and `KingdomAllianceObservation` history stores bounded machine provenance independently and is not foreign-key dependent on retained K4 operational rows.

Default operational retention is:

- promoted/rejected normalized candidate payload: redact after 30 days;
- terminal promoted/rejected candidate row: purge after 90 days;
- quarantined candidate row: retain for the longer 180-day review/replay window, then purge;
- completed/partial/failed/blocked batch: purge after 90 days only when no candidates remain; and
- disabled subscription scheduling/failure fields: compact after 30 days while preserving the subscription identity/state itself.

The retention action never deletes canonical promoted snapshots/observations. Batch deletion is additionally guarded by absence of candidate rows, preventing orphan-style operational pruning. Retention windows are configured in repository-controlled `config/kingdoms.php`; they are not tenant-entered values.

## 4. Privacy and secret handling

Retention reduces unnecessary operational normalized payload lifetime while preserving the minimum identifiers/hashes needed by accepted canonical provenance. It does not introduce a raw-response archive.

Source passwords, API tokens, cookies, authorization headers, recovery material and arbitrary endpoint configuration remain outside Kingdoms persistence and management surfaces. Operational health reports aggregate counts/timing states rather than payload bodies or raw source errors.

## 5. Operational health and alerting

`KingdomIngestionOperationalHealth` exposes bounded aggregate signals for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches. `attentionRequired` is monitoring evidence only; it grants no mutation authority and does not automatically alter roster/tracking/transfer/diplomacy state.

Repository-controlled thresholds are five minutes overdue, fifteen minutes stale-pending, twenty-five quarantined candidates and a sixty-minute recent-failure window. `kingdoms:ingestion-health --json` returns a non-zero exit status when attention is required so an operator monitoring system can alert without receiving private payload data.

## 6. Scheduler and destructive-operation controls

Slice E adds scheduled source reconciliation every five minutes and operational retention daily at 04:15 using `onOneServer()` and overlap protection. Existing acquisition scheduling remains isolated on the `kingdoms-ingestion` queue.

Neither maintenance task impersonates a User or gains cross-Alliance business authority. Source reconciliation changes only the affected subscription's operational approval/scheduling state; retention deletes/redacts only age-qualified K4 operational scaffolding.

Operators must not use database edits, cursor rewrites, candidate-state resets or operational row deletion as a replay/recovery mechanism. Approved manager replay remains the only K4 human re-drive path for quarantined candidates.

## 7. Identity, tenancy and decision-automation invariants

Slice E does not change the accepted automatic identity rules: stable game IDs only, existing owning-Alliance roster/tracking targets only, current Alliance/Kingdom context, and delegated K1/K3 recording actions.

No new path creates/reactivates roster or tracking state, corrects/invalidates game-Alliance observations, changes transfer/diplomacy/contact state, scores/ranks players or Alliances, or exposes K4 as a public inbound API/webhook.

## 8. Capacity and denial-of-service considerations

The operational-health query set is aggregate and bounded rather than row-by-row. The Slice E performance gate exercises 250 subscriptions, 40 failed batches and 110 candidates and requires the health snapshot to complete in at most eight SELECT queries.

This is repository-level capacity evidence for the generic operational control plane, not a claim about real-source throughput. Source-specific rate limits, network timeouts, schema behavior and production sizing remain source-enablement prerequisites.

## 9. Recovery and source enablement prerequisites

After restore or incident recovery, operators should first verify database/Redis/runtime health, then run or inspect the ingestion health and source-reconciliation state before resuming acquisition. Canonical promoted history is validated independently of operational retention.

Before any real networked adapter can be enabled, its review must still cover authorization/terms, endpoint ownership, DNS/redirect/private/metadata-address controls, TLS, egress, secret ownership, rate/timeout limits, schema/version policy, cursor semantics, monitoring and revocation behavior. Slice E repository acceptance is not that approval.

## 10. Validation evidence

Runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed Dependency Review `31552113152`, CodeQL `31552113044` and full CI `31552113042`.

Focused tests prove retention redaction/pruning, the longer quarantine review window, source-revocation disablement/idempotency, bounded operational attention signals and realistic-volume aggregate-query behavior. Full CI passed Pint (528 files), PHPStan/Larastan (374/374, zero errors), 428 PHPUnit/ParaTest tests with 9,736 assertions, frontend/build, clean migrations, immutable image build, ephemeral staging, backup/restore and image scan.

## 11. Residual risk decision

Repository risk is acceptable for K4-P5 because operational hardening fails closed and narrows retained data without increasing source or business authority. The principal residual risks are all real-source-specific and remain explicitly outside repository approval while the production adapter allowlist is empty.

See [Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md), [automated ingestion](../automated-ingestion.md), and [automated ingestion operations](../operations/kingdoms-automated-ingestion.md).
