# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current — `KINGDOMS-004` Accepted  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated Alliance Kingdoms workspaces, K4 manager ingestion control/replay, generic scheduled acquisition/maintenance contracts, and internal-only `kingdoms.*` events

**P4 inventory decision:** Existing accepted Kingdoms capability contracts remain the focused contract authority; accepted K4 extends this profile without changing the frozen P4 interface-inventory convention or creating a public Kingdoms API.

## 1. Boundary purpose and ownership

Kingdoms owns first-party workflows around neutral game identity, roster/history/intelligence, CSV migration/export, transfer planning, game-Alliance tracking/observations/diplomacy/contacts/intelligence, and accepted K4 ingestion control/promotion/scheduling/operations.

The external/public boundary remains narrow: there is no public Kingdoms API, public Kingdoms webhook event family, or public inbound ingestion endpoint.

## 2. Surface inventory

`routes/kingdoms.php` exposes authenticated/verified active-Alliance workspaces for settings; roster/history/intelligence/import/export; tracked game Alliances/intelligence/history/diplomacy/contacts; transfers/readiness/completion; and K4 manager ingestion status/control.

K4 manager routes include:

- `GET /alliance/kingdom-ingestion/manage`;
- password-confirmed `POST /alliance/kingdom-ingestion/subscriptions`;
- password-confirmed `PATCH /alliance/kingdom-ingestion/subscriptions/{subscription}/state`;
- password-confirmed `POST .../candidates/{candidate}/reject`; and
- password-confirmed `POST .../candidates/{candidate}/replay`.

There is no HTTP route to define source endpoints/credentials, stage arbitrary payloads, start/complete batches, invoke acquisition directly, promote arbitrary candidates, run retention/reconciliation/health, or receive external callbacks.

## 3. Callers, authorization and tenancy

Member-safe K1–K3 reads use `alliance.view`; K1–K4 management uses `kingdoms.manage` plus recent password confirmation for privileged human mutations; Alliance→Kingdom setting uses `alliance.manage`.

Tenant-owned identifiers are re-resolved beneath active Alliance/plan/tracking/subscription boundaries. Queue, adapter, source, cursor and candidate identity do not grant authorization. K4 maintenance tasks act only on persisted K4 operational state and gain no business-mutation authority.

## 4. Input and validation contracts

Stable game IDs remain the only automatic neutral identity keys. Display names/tags/handles/source labels are not match keys.

K4 subscription creation accepts only a bounded adapter key already present in the registry. Acquisition-capable adapters define a repository-controlled poll interval from 60–86,400 seconds and return a bounded page with source-window ID, optional opaque cursor and at most 250 records. Normalized records still pass target-specific staging bounds before promotion.

Retention/health thresholds are repository-controlled `config/kingdoms.php` values, not tenant input. Source reconciliation accepts only a bounded processing limit and resolves current adapter approval from the registry.

## 5. Output and disclosure contracts

K4 manager UI returns safe Alliance/Kingdom, adapter key/version/label/scheduling capability, subscription state/health/cursor/timing/circuit code, recent batch status/counts/failure code/next cursor, and recent candidate target/IDs/timing/state/reason.

`kingdoms:ingestion-health --json` returns aggregate counts for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates, recent failed batches and `attentionRequired`.

Neither surface serializes candidate normalized payload bodies, arbitrary external raw responses, source credentials/headers/cookies, tenant-entered URLs, or public machine credentials. No `/api/v1` Kingdoms ingestion scope exists.

## 6. Internal actions, queries and services

Supported K4 internal contracts include adapter registry/acquisition definitions; subscription create/transition; due-work claim; batch start/complete; normalized candidate staging; player/game-Alliance promotion; candidate reject/replay; scheduler run orchestration; source reconciliation; operational retention; aggregate operational health; and Alliance-scoped ingestion projections.

These are application/domain contracts, not public source APIs. [Automated ingestion](../automated-ingestion.md) defines lifecycle/invariants.

## 7. Events, outbox and cross-domain consumers

Material Kingdoms mutations produce Audit/Platform outbox evidence. External exclusion remains enforced: `alliance.kingdom_updated` and every `kingdoms.*` event are rejected by Integrations webhook fan-out before subscription matching.

K4 lifecycle/replay events carry bounded IDs/state/count/hash metadata. Source reconciliation/retention/health do not create a new external event contract. Internal event names are not public compatibility promises; their external ineligibility is the enforced contract.

## 8. Commands, jobs and scheduled work

K4 operator/runtime commands are:

- `kingdoms:queue-ingestion --limit=100` — every minute, single-server/overlap protected;
- `kingdoms:reconcile-ingestion-sources --limit=1000` — every five minutes, single-server/overlap protected;
- `kingdoms:enforce-ingestion-retention` — daily at 04:15, single-server/overlap protected; and
- `kingdoms:ingestion-health --json` — on-demand monitoring command with non-zero attention exit status.

Acquisition jobs use bounded timeout/tries/backoff, per-subscription unique/overlap controls and durable database claim/cursor state. Production adapter allowlist is empty, so acquisition has no real source in default production state.

No crawler, scraper, OCR worker, browser/game-client bot, arbitrary curl command or public source callback exists.

## 9. Files, imports, exports and external dependencies

The material file contract remains the controlled [CSV migration](../csv-migration.md) flow. K4 adds no file upload/download contract.

K4 defines a generic acquisition interface but no accepted production external service dependency, endpoint or source credential. A concrete adapter requires separate source/network/security approval.

## 10. Failure, idempotency, versioning and compatibility

K4 adapter key/version is captured on subscriptions/batches and changed/missing versions fail closed. Source reconciliation disables active/paused subscriptions with bounded `source_unapproved` state rather than substituting another version.

Source-window uniqueness, deterministic candidate identity and promoted-history idempotency protect at-least-once retry. Cursor advances only after Completed/Partial outcomes; exact completed-window replay requires the same stored next cursor.

Operational retention may redact/prune K4 candidate/batch state after repository-controlled windows but cannot delete promoted K1/K3 canonical history or rewrite copied provenance. A concrete adapter's source schema/version/cursor/network behavior becomes compatibility-relevant only after separate source approval.

## 11. Explicit non-capabilities

Kingdoms does not provide public API/webhook ingestion; arbitrary manager URLs/headers/credentials; scraping/OCR/bots; an approved real game-data source; auto roster/tracking/membership/transfer/diplomacy/contact behavior; machine K3 correction/invalidation; cross-Alliance sharing; or scoring/ranking/recommendations.

Generic scheduler/maintenance mechanics are accepted, but production has zero approved ingestion adapters.

## 12. Focused contracts, evidence and related documentation

- [CSV migration](../csv-migration.md)
- [Automated ingestion](../automated-ingestion.md)
- [K4 exit report](../product/kingdoms-automated-ingestion-exit-report.md)
- [K4 Slice E validation](../product/kingdoms-automated-ingestion-slice-e-validation.md)
- [K4 Slice E security review](../security/kingdoms-automated-ingestion-operations-security-review.md)
- [K4 operations](../operations/kingdoms-automated-ingestion.md)
- [Kingdoms domain](../README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)

Whole-increment interface evidence is protected-green at `3e0976e8bdd32207bd6314011c26b94fa0f3c118`: Dependency Review `31556412455`, CodeQL `31556412413`, CI `31556412468`, 429 tests / 9,799 assertions.
