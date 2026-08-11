# Kingdoms interfaces

[← Kingdoms domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary boundary:** Authenticated roster/history/intelligence/CSV/transfer/diplomacy workspaces plus internal-only Kingdoms event contracts  
**P4 inventory decision:** Accepted Kingdoms capability set reused; no new focused P4 file

## 1. Boundary purpose and ownership

Kingdoms owns first-party Alliance workflows around neutral game-world identity, roster/snapshots/intelligence, controlled CSV migration/export, transfer planning/completion, and game-Alliance tracking/observations/diplomacy/contacts/intelligence.

Its accepted external boundary is deliberately narrow: **there is no public Kingdoms API or public Kingdoms webhook contract**. Kingdoms outbox events remain internal evidence/coordination contracts unless a separately approved integration contract is introduced.

## 2. Surface inventory

`routes/kingdoms.php` exposes authenticated, verified, active-Alliance workspaces for:

- Alliance Kingdom setting read/update;
- roster index/manage/history/intelligence;
- roster CSV import preview/show/commit and export;
- tracked Kingdom Alliance index/manage/intelligence/history;
- diplomacy and manager-private contacts;
- transfer plan index/manage/readiness/completion; and
- all accepted K1–K3 mutations under recent password confirmation.

Member-safe reads and manager-private reads are separate payload/permission classes.

## 3. Callers, authorization and tenancy

Member-safe Kingdoms reads require `alliance.view`. Roster/snapshot/import/transfer/tracking/observation/diplomacy/contact management requires `kingdoms.manage` plus recent password confirmation. Alliance→Kingdom setting uses `alliance.manage`.

All tenant-owned identifiers are re-resolved beneath the active Alliance/plan/tracking boundary. Global `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` references never grant tenant access.

## 4. Input and validation contracts

First-party mutations validate accepted lifecycle/state vocabularies and stable game-identity rules. Display names/tags/handles are never automatic neutral identity keys.

The versioned CSV interchange contract is [Controlled CSV migration](../csv-migration.md): `kingdoms-roster.v1`, exact ordered headers, `.csv` only, valid UTF-8/no NUL, maximum 1 MiB and 500 rows, preview-before-commit, stable-ID-only automatic matching, and explicit ambiguity resolution.

Transfer/diplomacy/observation inputs follow their accepted capability contracts and fail closed on Alliance-Kingdom drift or cross-tenant identity.

## 5. Output and disclosure contracts

Member payloads expose only member-safe roster/history/intelligence/transfer/diplomacy presentation. Manager-only notes, richer provenance, restricted blocker detail, diplomacy private terms/rationale, contact details, actor/import metadata, and management identifiers remain private.

CSV export supports `scope=member|management`; management scope requires `kingdoms.manage`, while member scope uses `alliance.view`. The response is private/no-store CSV with `nosniff` protection.

There is no `/api/v1/kingdoms`, roster, snapshot, transfer, diplomacy, or game-Alliance scope in the external machine API.

## 6. Internal actions, queries and services

The accepted Kingdoms capability set defines supported internal actions/queries/services for:

- [Roster](../roster.md)
- [Player snapshots](../snapshots.md)
- [Roster intelligence](../intelligence.md)
- [Controlled CSV migration](../csv-migration.md)
- [Transfer planning](../transfer-planning.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)

Consumers such as Alliances/Memberships/Authorization/Audit/Platform interact through supported references/actions rather than direct cross-domain state mutation.

## 7. Events, outbox and cross-domain consumers

Material Kingdoms mutations produce internal Audit/Platform-outbox evidence under accepted event names. External exclusion is part of the current contract:

- `alliance.kingdom_updated` is not externally contracted; and
- every `kingdoms.*` event is rejected by Integrations webhook fan-out before subscription matching.

Therefore even a webhook subscription containing `*` cannot receive current Kingdoms events. Public exposure requires an explicit future Integrations contract/schema/test set.

## 8. Commands, jobs and scheduled work

Accepted K1–K3 Kingdoms runtime adds no Kingdoms-specific CLI command, queue job, crawler, scraper, OCR worker, ingestion scheduler, autonomous transfer executor, or diplomacy automation.

Current behavior is predominantly synchronous request/query work plus shared Audit/outbox publication.

## 9. Files, imports, exports and external dependencies

The material file contract is [Controlled CSV migration](../csv-migration.md). The import page exposes schema version, exact headers, byte limit and row limit from the parser itself. Import is preview/resolve/commit rather than blind upload/mutation.

Kingdoms otherwise depends on PostgreSQL plus shared Alliance/Identity/Authorization/Audit/Platform infrastructure. It has no accepted external Kingshot API/game-feed dependency.

## 10. Failure, idempotency, versioning and compatibility

CSV schema version is `kingdoms-roster.v1`; header/order/encoding/limits/matching semantics are compatibility-relevant. Committed-import identity and drift checks prevent duplicate/retargeted application.

Exact snapshot/observation retries are deterministic; transfer completion is idempotent per participant; ambiguous identity fails for human resolution; Alliance-Kingdom drift blocks normal mutations instead of silently retargeting historical state.

Internal `kingdoms.*` event names are not public compatibility promises. Their external ineligibility **is** an enforced integration boundary.

## 11. Explicit non-capabilities

Kingdoms does not provide:

- public API routes/scopes;
- public webhook event families;
- scraping/OCR/bots or undocumented Kingshot API ingestion;
- automated game-data ingestion;
- cross-Alliance shared intelligence;
- automatic transfer execution/recommendations;
- automatic diplomacy transitions/negotiation; or
- name/tag/handle-based automatic neutral identity merging.

## 12. Focused contracts, evidence and related documentation

P4 reuses the accepted capability set rather than creating duplicate interface documents:

- [Roster](../roster.md)
- [Player snapshots](../snapshots.md)
- [Roster intelligence](../intelligence.md)
- [Controlled CSV migration](../csv-migration.md)
- [Transfer planning](../transfer-planning.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)

Related documentation:

- [Kingdoms domain](../README.md)
- [Kingdoms security](../security/README.md)
- [Kingdoms operations](../operations/README.md)
- [Integrations interfaces](../../integrations/interfaces/README.md)
- [Integrations webhooks](../../integrations/webhooks.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
