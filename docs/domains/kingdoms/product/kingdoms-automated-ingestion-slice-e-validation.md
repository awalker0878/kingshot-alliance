# KINGDOMS-004 Slice E validation

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Status:** Complete  
**Scope:** `K4-P5` / Slice E — operations, review, retention, revocation and capacity hardening  
**Runtime candidate:** `eb706a96c9c875dd41e932e0691e4258f33e01f1`

## Delivered behavior

Slice E completes the generic K4 operational hardening around the accepted P1–P4 ingestion runtime. It does **not** add or approve a concrete production source.

- repository-controlled retention windows bound operational candidate/batch state;
- promoted/rejected normalized candidate payloads are redacted before terminal rows are later purged;
- quarantined candidates receive a longer review/replay retention window;
- terminal batches are pruned only after their candidate rows are gone;
- disabled subscriptions are preserved but stale scheduling/failure/circuit fields are compacted;
- source approval is continuously reconciled against the current adapter key/version registry;
- revoked/missing adapter approval disables active/paused subscriptions with bounded `source_unapproved` state and clears future scheduling/circuit state;
- aggregate operational health exposes bounded counts for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches;
- `kingdoms:ingestion-health --json` provides monitoring-friendly health output and a non-zero attention status;
- source reconciliation runs every five minutes with single-server/overlap protection;
- K4 operational retention runs daily at 04:15 with single-server/overlap protection; and
- a realistic-volume performance gate requires the operational-health snapshot to remain a bounded aggregate query set.

Production `config/kingdoms.php` still has an empty `ingestion_adapters` allowlist. No endpoint, credential, scraper, OCR/browser/game-client automation or source-specific network dependency is introduced.

## Retention and canonical-history protection

Default repository-controlled retention is 30 days for terminal normalized payload bodies, 90 days for terminal promoted/rejected candidate rows, 180 days for quarantined candidate rows, 90 days for terminal batches with no remaining candidates, and 30 days before disabled-subscription scheduling/failure compaction.

These windows apply only to K4 operational scaffolding. Promoted K1/K3 `PlayerSnapshot` and `KingdomAllianceObservation` rows retain their accepted append history and bounded machine provenance independently of the operational subscription/batch/candidate rows.

Retention does not delete promoted canonical observations, rewrite source provenance, reset candidates for replay or mutate business history. Quarantined rows are intentionally retained longer than ordinary terminal rows because they are the only state eligible for controlled human review/replay.

## Source-revocation and recovery behavior

`ReconcileKingdomIngestionSources` rechecks active/paused subscriptions against the live repository/operator adapter registry under row locks. Missing adapter approval or version drift disables the subscription and records bounded `source_unapproved` state rather than substituting, retargeting or continuing acquisition.

Restoring a source requires a reviewed repository/config approval; direct database edits are not an approved recovery procedure. After incident restore, operators can verify aggregate health, source-revocation state and operational/canonical separation before resuming acquisition.

See [Slice E security/privacy review](../security/kingdoms-automated-ingestion-operations-security-review.md) and [automated ingestion operations](../operations/kingdoms-automated-ingestion.md).

## Observability and capacity evidence

Operational health is deliberately aggregate and payload-free. It exposes safe counts/timing state and an attention decision without serializing normalized candidate bodies, source secrets or raw source errors.

The Slice E performance test loads 250 subscriptions, 40 failed batches and 110 candidates, verifies expected health totals, and requires the snapshot to execute in at most eight SELECT queries. This proves the generic operations view does not grow into an N+1 query pattern at representative repository volume.

This is not a real-source throughput SLO. Source authorization, network behavior, rate limits, cursor semantics, schema/version policy, production sizing and concrete alert thresholds remain source-enablement prerequisites where they depend on the future provider.

## Executable validation

Focused Slice E tests prove:

- terminal candidate payload redaction precedes later candidate/batch pruning;
- the subscription remains after operational candidate/batch pruning;
- quarantined candidates survive the ordinary terminal window and prune only after the longer review window;
- disappearing adapter approval disables the subscription with `source_unapproved`, clears scheduling state and reconciles idempotently;
- a clean operational-health snapshot reports no attention requirement;
- revoked source state is surfaced as an attention signal; and
- realistic operational volume remains within the eight-SELECT aggregate-query gate.

Existing K1–K3 and K4-P1–P4 architecture, tenancy, migration, accessibility, integration exclusion, replay, cursor and idempotency tests remain additive.

## Protected candidate evidence

Runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1` passed:

- Dependency Review `31552113152` — success;
- CodeQL `31552113044` — success;
- CI `31552113042` — success;
- Pint — 528 files;
- PHPStan/Larastan — 374/374, 0 errors;
- ParaTest/PHPUnit — 428 tests / 9,736 assertions;
- frontend ESLint/Prettier/Vue-TypeScript/build — success;
- clean PostgreSQL migrations — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success; and
- image scan — success.

The runtime sequence included retention-test timestamp/aging corrections before the accepted candidate; the final candidate contains those fixes plus the capacity gate.

## Real-source boundary

P5 repository acceptance still cannot prove a future provider's authorization/terms, endpoint ownership, redirect/DNS/private-address behavior, TLS/egress controls, secret handling, rate/timeout semantics, schema/version behavior, cursor guarantees or provider-side revocation response because production source configuration is empty.

Those remain explicit source-enablement prerequisites. No production source/cutover is approved by this validation.

## Gate decision

`K4-P5` / Slice E is **Complete** at runtime candidate `eb706a96c9c875dd41e932e0691e4258f33e01f1`.

`K4-P6` whole-increment acceptance is selected next, subject to the exact containing evidence/status head that records the P5 Complete / P6 Current transition passing Dependency Review, CodeQL and full CI. P6 must not infer real-source approval from generic repository acceptance.
