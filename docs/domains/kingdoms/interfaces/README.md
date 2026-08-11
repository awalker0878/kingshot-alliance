# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 manager ingestion control/replay, generic scheduled acquisition contract, and internal-only `kingdoms.*` events

**P4 inventory decision:** Existing accepted Kingdoms capability contracts remain the focused contract authority; this profile inventories the first-party and machine boundaries without creating a public Kingdoms API or duplicating route-by-route implementation documentation.

## 1. Boundary purpose and ownership

Kingdoms owns first-party workflows around neutral game identity, roster/history/intelligence, CSV migration/export, transfer planning, game-Alliance tracking/observations/diplomacy/contacts/intelligence, and K4 ingestion control/promotion/scheduling.

The external/public boundary remains narrow: there is no public Kingdoms API, public Kingdoms webhook event family, or public inbound ingestion endpoint.

## 2. Surface inventory

`routes/kingdoms.php` exposes authenticated/verified active-Alliance workspaces for settings; roster/history/intelligence/import/export; tracked game Alliances/intelligence/history/diplomacy/contacts; transfers/readiness/completion; and K4 manager ingestion status/control.

K4 manager routes include:

- `GET /alliance/kingdom-ingestion/manage`;
- password-confirmed `POST /alliance/kingdom-ingestion/subscriptions`;
- password-confirmed `PATCH /alliance/kingdom-ingestion/subscriptions/{subscription}/state`;
- password-confirmed `POST .../candidates/{candidate}/reject`;
- password-confirmed `POST .../candidates/{candidate}/replay`.

There is no HTTP route to define source endpoints/credentials, stage arbitrary payloads, start/complete batches, invoke acquisition directly, promote arbitrary candidates, or receive external callbacks.

## 3. Callers, authorization and tenancy

Member-safe K1–K3 reads use `alliance.view`; K1–K4 management uses `kingdoms.manage` plus recent password confirmation for privileged human mutations; Alliance→Kingdom setting uses `alliance.manage`.

Tenant-owned identifiers are re-resolved beneath active Alliance/plan/tracking/subscription boundaries. Queue, adapter, source, cursor and candidate identity do not grant authorization.

## 4. Input and validation contracts

Stable game IDs remain the only automatic neutral identity keys. Display names/tags/handles/source labels are not match keys.

K4 subscription creation accepts only a bounded adapter key already present in the registry. Acquisition-capable adapters define a repository-controlled poll interval from 60–86,400 seconds and return a bounded page with source-window ID, optional opaque cursor and at most 250 records. Normalized records still pass target-specific P1 staging bounds before P2/P3 promotion.

The existing roster CSV contract remains `kingdoms-roster.v1`; transfer/diplomacy/observation inputs retain accepted contracts.

## 5. Output and disclosure contracts

K4 manager UI returns safe Alliance/Kingdom, adapter key/version/label/scheduling capability, subscription state/health/cursor/timing/circuit code, recent batch status/counts/failure code/next cursor, and recent candidate target/IDs/timing/state/reason.

It does **not** serialize candidate normalized payload bodies, arbitrary external raw responses, source credentials/headers/cookies, tenant-entered URLs, or public machine credentials. Existing member-vs-manager K1–K3 disclosure rules remain unchanged.

No `/api/v1` Kingdoms roster/snapshot/transfer/diplomacy/ingestion scope exists.

## 6. Internal actions, queries and services

Supported K4 internal contracts include adapter registry/acquisition definitions; subscription create/transition; due-work claim; batch start/complete; normalized candidate staging; player/game-Alliance promotion; candidate reject/replay; scheduler run orchestration; and Alliance-scoped ingestion projections.

These are application/domain contracts, not public source APIs. [Automated ingestion](../automated-ingestion.md) defines lifecycle/invariants.

Existing K1–K3 internal contracts remain documented by [Roster](../roster.md), [Snapshots](../snapshots.md), [Roster intelligence](../intelligence.md), [CSV migration](../csv-migration.md), [Transfer planning](../transfer-planning.md), and [Alliance intelligence](../alliance-intelligence.md).

## 7. Events, outbox and cross-domain consumers

Material Kingdoms mutations produce Audit/Platform outbox evidence. External exclusion remains enforced: `alliance.kingdom_updated` and every `kingdoms.*` event are rejected by Integrations webhook fan-out before subscription matching.

K4 adds internal ingestion lifecycle/replay events with bounded IDs/state/count/hash metadata. Internal event names are not public compatibility promises; their external ineligibility is the enforced contract.

## 8. Commands, jobs and scheduled work

K4-P4 adds `kingdoms:queue-ingestion --limit=100`, scheduled every minute with `onOneServer()` / `withoutOverlapping(10)`, plus `RunKingdomIngestionSubscriptionJob` on dedicated `kingdoms-ingestion` Horizon queue.

Jobs use bounded timeout/tries/backoff, per-subscription unique/overlap controls and durable database claim/cursor state. Production adapter allowlist is empty, so the command has no real source to acquire in default production state.

No crawler, scraper, OCR worker, browser/game-client bot, arbitrary curl command or public source callback exists.

## 9. Files, imports, exports and external dependencies

The material file contract remains the controlled roster CSV flow. K4 adds no file upload/download contract.

K4-P4 defines a generic acquisition interface but no accepted production external service dependency, endpoint or source credential. A concrete adapter requires separate source/network/security approval.

## 10. Failure, idempotency, versioning and compatibility

K4 adapter key/version is captured on subscriptions/batches and changed/missing versions fail closed. Source-window uniqueness, deterministic candidate identity and promoted-history idempotency protect at-least-once retry.

Cursor advances only after Completed/Partial outcomes; exact completed-window replay requires the same stored next cursor. Queue delivery controls reduce duplicates but database/domain idempotency remains authoritative.

A concrete adapter's source schema/version/cursor/network behavior becomes compatibility-relevant only after separate source approval.

## 11. Explicit non-capabilities

Kingdoms does not provide public API/webhook ingestion; arbitrary manager URLs/headers/credentials; scraping/OCR/bots; an approved real game-data source; auto roster/tracking/membership/transfer/diplomacy/contact behavior; machine K3 correction/invalidation; cross-Alliance sharing; or scoring/ranking/recommendations.

Generic scheduler mechanics are implemented, but production has zero approved ingestion adapters.

## 12. Focused contracts, evidence and related documentation

- [Automated ingestion](../automated-ingestion.md)
- [K4 Slice D validation](../product/kingdoms-automated-ingestion-slice-d-validation.md)
- [K4 Slice D security review](../security/kingdoms-automated-ingestion-scheduler-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
