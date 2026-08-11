# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 manager control plane, internal K4 promotion services, and internal-only `kingdoms.*` events  
**P4 inventory decision:** Accepted Kingdoms capability set reused; K4-P3 extends the living profile without creating a separate frozen-P4 focused contract

## 1. Boundary purpose and ownership

Kingdoms owns neutral game identity plus Alliance-owned roster/history/intelligence, controlled CSV migration, transfer planning, game-Alliance intelligence/diplomacy, and K4 ingestion workflows. K4 through P3 adds internal player-snapshot and game-Alliance factual-observation promotion services; neither is a public source or machine interface.

## 2. Surface inventory

Existing authenticated K1–K4 first-party routes remain unchanged from K4-P1. P2/P3 add **no HTTP route** for candidate promotion, arbitrary source payloads, credentials, external callbacks, or public machine use.

The manager ingestion routes remain status/control, subscription creation/state transition, and quarantined-candidate rejection under `/alliance/kingdom-ingestion`.

## 3. Callers, authorization and tenancy

Human reads/mutations retain `alliance.view`, `kingdoms.manage`, `alliance.manage`, active-Alliance context, and password-confirm requirements. Internal promotion accepts only tenant-owned subscription/candidate identifiers and re-resolves batch/alliance/current-Kingdom plus the required existing tenant relationship before mutation.

Neutral game identity, adapter identity, source record identity, and candidate state never grant tenant authority.

## 4. Input and validation contracts

Stable game IDs remain the only automatic identity keys. `player_snapshot` promotion resolves stable player ID plus existing owning-Alliance roster. `alliance_observation` promotion resolves stable game-Alliance ID plus existing active owning-Alliance tracking.

Names/tags/handles/source labels are never target match keys. Machine game-Alliance payloads contain factual observation fields only and cannot request correction/invalidation. The accepted controlled roster file contract remains [CSV migration](../csv-migration.md).

## 5. Output and disclosure contracts

Ordinary member history exposes factual observation values, capture time, and source only. Manager history may additionally expose bounded machine provenance for ingestion-origin observations.

Candidate normalized payload bodies, source credentials/raw responses, diplomacy/contact private data, and public machine credentials remain excluded. No `/api/v1` Kingdoms ingestion/promotion scope exists.

## 6. Internal actions, queries and services

K4 includes P1 adapter/subscription/batch/candidate services plus `PromoteKingdomIngestionPlayerSnapshot` and `PromoteKingdomIngestionAllianceObservation`, delegating to the accepted K1/K3 record actions with explicit machine provenance and no fabricated User actor.

Promotion never invokes roster/tracking creation or tracking reactivation. Machine game-Alliance promotion cannot invoke correction/invalidation behavior.

## 7. Events, outbox and cross-domain consumers

Machine player observations reuse internal `kingdoms.player_snapshot_recorded`; machine game-Alliance observations reuse internal `kingdoms.alliance_intelligence_observation_recorded`; successful candidate transitions emit internal `kingdoms.ingestion_candidate_promoted`.

Every `kingdoms.*` event remains externally ineligible through Integrations webhook fan-out. Machine promotion does not emit the human K3 correction event.

## 8. Commands, jobs and scheduled work

K4 through P3 adds no command, acquisition queue job, scheduler, source poller, crawler, scraper, OCR worker, bot, cursor loop, retry worker, or replay worker. Those remain K4-P4 concerns after the P3 evidence gate.

## 9. Files, imports, exports and external dependencies

The accepted [controlled roster CSV contract](../csv-migration.md) remains the material Kingdoms file interface. K4 through P3 adds no new import/export or external dependency. Production adapter configuration remains empty.

## 10. Failure, idempotency, versioning and compatibility

Exact promoted-candidate retry resolves the same canonical record; later distinct capture remains append history. Adapter version is rechecked at promotion time. Missing/changed versions, context drift, stable-ID ambiguity, and missing/inactive tenant relationships quarantine.

Canonical history correlates to operational ingestion through copied bounded provenance and safe promoted-record IDs, not retention-coupling FKs.

## 11. Explicit non-capabilities

No real game-data source, scheduler/worker/cursor/retry loop, public API/webhook ingestion, arbitrary manager URLs/credentials, scraping/OCR/bots, roster/tracking auto-creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact automation, cross-Alliance sharing, scoring/ranking, or recommendations are approved through P3.

## 12. Focused contracts, evidence and related documentation

- [Kingdoms domain](../README.md)
- [Controlled CSV migration](../csv-migration.md)
- [Automated ingestion](../automated-ingestion.md)
- [Player snapshots](../snapshots.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)
- [K4 Slice C validation](../product/kingdoms-automated-ingestion-slice-c-validation.md)
- [K4 Slice C security review](../security/kingdoms-automated-ingestion-alliance-promotion-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
