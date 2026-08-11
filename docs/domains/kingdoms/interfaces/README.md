# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 manager ingestion control plane, and internal-only `kingdoms.*` event contracts

## 1. Boundary purpose and ownership

Kingdoms owns first-party workflows around neutral game identity, roster/history/intelligence, CSV migration/export, transfer planning, game-Alliance tracking/observations/diplomacy/contacts/intelligence, and the K4 generic ingestion control plane.

The external boundary remains narrow: there is no public Kingdoms API, public Kingdoms webhook event family, or public inbound ingestion endpoint.

## 2. Surface inventory

`routes/kingdoms.php` exposes authenticated/verified active-Alliance workspaces for settings; roster/history/intelligence/import/export; tracked game Alliances/intelligence/history/diplomacy/contacts; transfers/readiness/completion; and K4 manager ingestion status/control.

K4-P1 routes are:

- `GET /alliance/kingdom-ingestion/manage`;
- password-confirmed `POST /alliance/kingdom-ingestion/subscriptions`;
- password-confirmed `PATCH /alliance/kingdom-ingestion/subscriptions/{subscription}/state`;
- password-confirmed `POST /alliance/kingdom-ingestion/subscriptions/{subscription}/candidates/{candidate}/reject`.

There is no HTTP route to define an endpoint/credential, stage arbitrary source payloads, start/complete batches, promote candidates, or receive external callbacks.

## 3. Callers, authorization and tenancy

Member-safe K1–K3 reads use `alliance.view`; K1–K4 management uses `kingdoms.manage` plus recent password confirmation for mutations; Alliance→Kingdom setting uses `alliance.manage`.

Tenant-owned identifiers are re-resolved beneath active Alliance/plan/tracking/subscription boundaries. K4 adapter/source/cursor/candidate identity does not grant authorization.

## 4. Input and validation contracts

Stable game IDs remain the only automatic neutral identity keys. Display names/tags/handles/source labels are not match keys.

K4 subscription creation accepts only a bounded adapter key that must already resolve in `KingdomIngestionAdapterRegistry`. State transitions accept the enum lifecycle. Internal normalized candidates accept only target-specific approved keys/bounds for `player_snapshot` or `alliance_observation`; missing stable identity quarantines.

The existing CSV contract remains `kingdoms-roster.v1`; transfer/diplomacy/observation inputs retain their accepted contracts.

## 5. Output and disclosure contracts

K4 manager UI returns safe Alliance/Kingdom, adapter key/version/label, subscription status/health, recent batch status/counts/failure code, and recent candidate target/IDs/timing/state/reason.

It does **not** serialize candidate normalized payload bodies, arbitrary external raw responses, source credentials/headers/cookies, tenant-entered URLs, or public machine credentials. Existing member-vs-manager K1–K3 disclosure rules remain unchanged.

No `/api/v1` Kingdoms roster/snapshot/transfer/diplomacy/ingestion scope exists.

## 6. Internal actions, queries and services

Supported K4-P1 internal contracts include adapter registry definitions; subscription create/transition; batch start/complete; normalized candidate staging; candidate rejection; and Alliance-scoped ingestion query projections.

These are application/domain contracts, not public source APIs. [Automated game-data ingestion](../automated-ingestion.md) defines their lifecycle/invariants.

Existing K1–K3 internal contracts remain documented by [Roster](../roster.md), [Snapshots](../snapshots.md), [Roster intelligence](../intelligence.md), [CSV migration](../csv-migration.md), [Transfer planning](../transfer-planning.md), and [Alliance intelligence](../alliance-intelligence.md).

## 7. Events, outbox and cross-domain consumers

Material Kingdoms mutations produce Audit/Platform outbox evidence. External exclusion remains enforced: `alliance.kingdom_updated` and every `kingdoms.*` event are rejected by Integrations webhook fan-out before subscription matching.

K4 adds internal `kingdoms.ingestion_*` events with bounded IDs/state/count/hash metadata. Internal event names are not public compatibility promises; their external ineligibility is the enforced contract.

## 8. Commands, jobs and scheduled work

K4-P1 adds **no background processing** command/job/schedule. There is no source poller, crawler, scraper, OCR worker, bot, queue partition, cursor loop, retry worker, or replay worker.

Internal batch/candidate actions intentionally exist before K4-P4 so later background work can depend on a tested domain contract rather than write tables directly.

## 9. Files, imports, exports and external dependencies

The material file contract remains the controlled roster CSV flow. K4-P1 adds no file upload/download contract.

Production `config/kingdoms.php` has an empty `ingestion_adapters` list, so K4 currently has no accepted external game-data service dependency or source credential.

## 10. Failure, idempotency, versioning and compatibility

K4 adapter key/version is captured on subscriptions/batches and changed/missing versions fail closed. Source-window uniqueness and deterministic candidate identity protect exact retries. Completed batch outcomes cannot be rewritten to another result. Alliance-Kingdom drift blocks new work/re-activation rather than retargeting history.

A concrete adapter's source schema/version/cursor behavior becomes compatibility-relevant only after separate source approval.

## 11. Explicit non-capabilities

Kingdoms currently does not provide public API/webhook ingestion; arbitrary manager URLs/headers/credentials; scraping/OCR/bots; real game-data acquisition; K4 scheduler/worker; automatic candidate promotion; auto roster/tracking/transfer/diplomacy; cross-Alliance sharing; or scoring/ranking/recommendations.

## 12. Focused contracts, evidence and related documentation

- [Automated ingestion](../automated-ingestion.md)
- [K4 Slice A validation](../product/kingdoms-automated-ingestion-slice-a-validation.md)
- [K4 Slice A security review](../security/kingdoms-automated-ingestion-foundation-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
