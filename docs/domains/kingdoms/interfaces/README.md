# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 manager control plane, internal K4 promotion services, and internal-only `kingdoms.*` events  
**P4 inventory decision:** Accepted Kingdoms capability set reused; K4-P2 extends the living profile without creating a separate frozen-P4 focused contract

## 1. Boundary purpose and ownership

Kingdoms owns neutral game identity plus Alliance-owned roster/history/intelligence, controlled CSV migration, transfer planning, game-Alliance intelligence/diplomacy, and K4 ingestion workflows. K4-P2 adds an internal player-snapshot promotion service; it does not create a public source or machine interface.

## 2. Surface inventory

Existing authenticated K1–K4 first-party routes remain unchanged from K4-P1. K4-P2 adds **no HTTP route** for candidate promotion, arbitrary source payloads, credentials, external callbacks, or public machine use.

The manager ingestion routes remain status/control, subscription creation/state transition, and quarantined-candidate rejection under `/alliance/kingdom-ingestion`.

## 3. Callers, authorization and tenancy

Human reads/mutations retain `alliance.view`, `kingdoms.manage`, `alliance.manage`, active-Alliance context, and password-confirm requirements. Internal promotion accepts only tenant-owned subscription/candidate identifiers and re-resolves batch/alliance/current-Kingdom context before mutation.

Neutral game identity, adapter identity, source record identity, and candidate state never grant tenant authority.

## 4. Input and validation contracts

Stable game IDs remain the only automatic identity keys. K4-P2 accepts only a normalized `player_snapshot` candidate whose bounded fields were staged through the registered adapter contract.

Promotion rechecks adapter key/version/target support and resolves the stable player ID in the captured Kingdom. Names/tags/handles/source labels are never target match keys. The accepted controlled roster file contract remains [CSV migration](../csv-migration.md).

## 5. Output and disclosure contracts

Ordinary member snapshot history continues to expose observation values, capture time, and source only. Manager history may additionally expose bounded machine provenance for ingestion-origin snapshots.

Candidate normalized payload bodies, source credentials, arbitrary raw responses, and public machine credentials remain excluded. No `/api/v1` Kingdoms ingestion/snapshot scope exists.

## 6. Internal actions, queries and services

K4 includes the P1 adapter/subscription/batch/candidate services plus `PromoteKingdomIngestionPlayerSnapshot`, which delegates accepted facts to `RecordPlayerSnapshot` with explicit machine provenance and no fabricated User actor.

K4-P2 never invokes roster creation. Unknown, ambiguous, revoked-source, or out-of-context targets quarantine.

## 7. Events, outbox and cross-domain consumers

Newly accepted machine snapshots reuse internal `kingdoms.player_snapshot_recorded`; candidate promotion adds internal `kingdoms.ingestion_candidate_promoted`. Both carry bounded identifiers/provenance. Every `kingdoms.*` event remains externally ineligible through Integrations webhook fan-out.

## 8. Commands, jobs and scheduled work

K4-P2 adds no command, queue job, scheduler, source poller, crawler, scraper, OCR worker, bot, cursor loop, retry worker, or replay worker. Those remain K4-P4 concerns.

## 9. Files, imports, exports and external dependencies

The accepted [controlled roster CSV contract](../csv-migration.md) remains the material Kingdoms file interface. K4-P2 adds no new import/export or external dependency. Production adapter configuration remains empty.

## 10. Failure, idempotency, versioning and compatibility

Promotion exact retry resolves the same candidate/snapshot. Later distinct capture time remains append history. Source adapter version is rechecked at promotion time; missing/changed versions quarantine. Candidate/business-history correlation uses bounded provenance/record identity rather than a retention-coupling FK.

## 11. Explicit non-capabilities

No real game-data source, scheduler/worker, public API/webhook ingestion, arbitrary manager URLs/credentials, scraping/OCR/bots, roster auto-enrollment, game-Alliance observation promotion, transfer/diplomacy automation, cross-Alliance sharing, scoring/ranking, or recommendations are approved by P2.

## 12. Focused contracts, evidence and related documentation

- [Kingdoms domain](../README.md)
- [Controlled CSV migration](../csv-migration.md)
- [Automated ingestion](../automated-ingestion.md)
- [Player snapshots](../snapshots.md)
- [K4 Slice B validation](../product/kingdoms-automated-ingestion-slice-b-validation.md)
- [K4 Slice B security review](../security/kingdoms-automated-ingestion-player-promotion-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
