# Kingdoms security profile

[← Kingdoms domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Kingdoms  
**Code owner:** `app/Domain/Kingdoms`  
**Primary security boundary:** Alliance-owned management of neutral game reference data and private intelligence workflows, separated from tenant authorization and public machine exposure

## 1. Security purpose and scope

Kingdoms protects neutral game reference identity plus Alliance-owned roster, snapshot, descriptive intelligence, CSV migration, transfer planning, tracked game-Alliance observations, diplomacy, manager-private contacts, and intelligence presentation.

The domain has accepted `KINGDOMS-001` through `KINGDOMS-003` security evidence. `KINGDOMS-004` automated-ingestion planning is separately indexed below and remains **no-runtime planning** until its own sliced implementation is accepted.

## 2. Assets and sensitive data

Neutral `Kingdom`, `KingdomPlayer`, and `KingdomAlliance` identity are game reference data and do not grant application access. Alliance-owned roster annotations, snapshots/import provenance, transfer plans/readiness/blockers/groups, tracked observations, diplomacy state/history, manager notes, and diplomacy contacts are tenant-private business data.

Diplomacy contacts and other manager-private narrative have a narrower audience than member-safe descriptive intelligence. Credentials, recovery secrets, phone/address details beyond the explicitly supported coordination fields, or other unrelated sensitive data are not accepted Kingdoms content.

## 3. Actors, authentication and authorization

Member-safe Kingdoms reads require authenticated verified active-Alliance context plus `alliance.view`. Management mutations require `kingdoms.manage` and recent password confirmation where defined.

Game identity, coordinator assignment, tracked Alliance identity, contact records, or display names never grant application authorization. Platform authority remains separate.

## 4. Tenant and privacy boundaries

Neutral game identities may be shared/referenceable across tenants, but all observations, roster ownership, transfer state, diplomacy, contacts, notes, and derived intelligence are owned by one Alliance. Submitted IDs are re-resolved beneath the active tenant where the state is Alliance owned.

Tracking is constrained to the Alliance's active current Kingdom for normal mutation. Historical records are preserved when the Alliance later changes Kingdom; stale-context mutation fails closed rather than silently retargeting history.

## 5. Trust boundaries and data flows

Material current-runtime boundaries include authenticated member/manager → Kingdoms workspaces, controlled CSV upload → parser/validation → transactional import, Alliance-owned observations/snapshots → descriptive aggregation, transfer workflow → completion/roster handoff, and Platform/Audit/outbox infrastructure used for evidence.

No automated scraping/OCR/bot ingestion, public Kingdoms API, or public Kingdoms webhook boundary is accepted in K1–K3. `KINGDOMS-004` P0 planning defines a future approved-source ingestion boundary but adds no runtime source, worker, scheduler, endpoint, or credential path yet.

## 6. Threats, abuse cases and controls

Primary current-runtime threats include cross-Alliance roster/intelligence access, treating shared game identity as tenant authorization, ambiguous name/tag/handle auto-linking, destructive history rewriting, duplicate snapshot/import/observation transitions, unsafe CSV input, leaking transfer blockers/contacts, automated diplomacy inference, scoring/ranking becoming hidden decision automation, and accidental public integration exposure.

Controls include stable neutral IDs, explicit Alliance-owned relationships, tenant-scoped re-resolution, append-oriented observations/snapshots/history, exact-retry idempotency, controlled correction/invalidation rather than overwrite, bounded validated CSV parsing, manager-private contact boundaries, explicit human diplomacy transitions, descriptive-only intelligence, and explicit external non-capabilities.

The K4 P0 review additionally addresses source approval, arbitrary-destination/SSRF risk, secret leakage, machine provenance, quarantine, at-least-once replay, queue isolation, and prevention of automated enrollment/diplomacy/transfer/scoring decisions before runtime implementation begins.

## 7. Integrity, concurrency and idempotency

Snapshots and observations preserve history rather than mutating prior facts. Exact retries are idempotent; corrections append replacement/invalidation where specified. Missing observations are not silently interpreted as zero.

Transfer state transitions and completion/handoff follow explicit workflow rules. Diplomacy state changes only through authorized human actions; expiry/review signals never auto-change relationships. Concurrency/locking rules are documented in the focused K1–K3 reviews and living capability contracts.

K4 planning requires any future automated promotion to reuse the accepted K1/K3 recording actions and deterministic retry semantics rather than writing directly to observation tables.

## 8. Secrets and credential handling

Current Kingdoms runtime owns no authentication/API/webhook credential and does not accept credentials/recovery secrets as contact or notes content. CSV files, notes, audit/outbox metadata, and diagnostics must not become a path for secret ingestion.

Manager-private diplomacy contacts store only supported coordination data; they are not a general address book, identity-verification system, or secret store.

K4 P0 additionally prohibits plaintext source credentials, session cookies, authorization headers, or arbitrary tenant-supplied source secrets in Kingdoms persistence/logging/audit/outbox. Concrete source authentication requires a separately approved secret owner before runtime enablement.

## 9. Destructive operations, retention and deletion

Kingdoms favors append/history-preserving correction for snapshots, observations, diplomacy history, and accepted workflow evidence. Archival/stale-context recovery preserves history rather than deleting it when an Alliance changes Kingdom.

Broader account/Alliance legal hold, retention, anonymization, and deletion are Platform-orchestrated while Kingdoms remains owner of domain-specific historical semantics.

K4 planning treats future batch/candidate ingestion state as bounded operational scaffolding rather than duplicate permanent business history; promoted observations retain their existing K1/K3 history rules.

## 10. Auditability, observability and evidence

Privileged Kingdoms mutations create attributable audit/internal outbox evidence where required. Security diagnosis distinguishes tenant/permission failures, current-Kingdom drift, source/import validation, snapshot/observation identity, transfer state, diplomacy/contact privacy, and descriptive aggregation.

K1–K3 accepted reviews document targeted threat/control/test evidence. Repository validation also enforces tenant isolation, authorization, performance/query bounds, no public Kingdoms machine contracts, and other accepted increment constraints.

K4 planning requires safe machine/source provenance and failure-class/count/timing diagnostics without raw secret or unbounded external response logging.

## 11. Residual risks and explicit non-capabilities

Human-entered game observations/diplomacy/contact notes can be inaccurate or inappropriate; the application intentionally avoids automated threat scoring, ranking, recommendation, diplomacy inference, or transfer decision automation that could falsely amplify such data.

The current runtime does not perform scraping/OCR/bot ingestion, automatically merge/link by names/tags/handles, expose public Kingdoms APIs/webhooks, collect general contact/credential secrets, or treat game identity as application authority.

`KINGDOMS-004` planning does not change that runtime statement. Its future initial scope also excludes arbitrary manager-configured network fetches, public inbound ingestion endpoints, automatic roster/tracking creation, diplomacy/transfer automation, and `KINGDOMS-005` cross-Alliance sharing.

## 12. Focused reviews and related documentation

### `KINGDOMS-001`

- [Foundation security review](kingdoms-foundation-security-review.md)
- [Roster security review](kingdoms-roster-security-review.md)
- [Snapshot security review](kingdoms-snapshot-security-review.md)
- [Roster intelligence security review](kingdoms-intelligence-security-review.md)
- [CSV migration security review](kingdoms-csv-security-review.md)
- [Whole-increment roster/intelligence security review](kingdoms-roster-intelligence-security-review.md)

### `KINGDOMS-002`

- [Transfer foundation security review](kingdoms-transfer-planning-foundation-security-review.md)
- [Transfer participant security review](kingdoms-transfer-participant-security-review.md)
- [Transfer group security review](kingdoms-transfer-group-security-review.md)
- [Transfer readiness security review](kingdoms-transfer-readiness-security-review.md)
- [Transfer completion security review](kingdoms-transfer-completion-security-review.md)
- [Whole-increment transfer-planning security review](kingdoms-transfer-planning-security-review.md)

### `KINGDOMS-003`

- [K3-P0 security decisions](kingdoms-alliance-intelligence-p0-security-review.md)
- [Alliance tracking security review](kingdoms-alliance-tracking-security-review.md)
- [Observation security review](kingdoms-alliance-observation-security-review.md)
- [Diplomacy security review](kingdoms-alliance-diplomacy-security-review.md)
- [Diplomacy contact security review](kingdoms-alliance-diplomacy-contact-security-review.md)
- [Intelligence dashboard security review](kingdoms-alliance-intelligence-dashboard-security-review.md)
- [Whole-increment Alliance intelligence security review](kingdoms-alliance-intelligence-security-review.md)

### `KINGDOMS-004` — planning only

- [K4-P0 automated ingestion security/privacy review](kingdoms-automated-ingestion-p0-security-review.md)
- [K4 planning scope](../product/kingdoms-automated-ingestion-increment.md)
- [K4 implementation plan](../product/kingdoms-automated-ingestion-implementation-plan.md)
- [K4-P0 design decisions](../product/kingdoms-automated-ingestion-p0-decisions.md)

No K4 runtime security review is accepted yet because no K4 runtime exists.

### Current contracts and shared policy

- [Kingdoms domain contract](../README.md)
- [Roster](../roster.md)
- [Snapshots](../snapshots.md)
- [Roster intelligence](../intelligence.md)
- [CSV migration](../csv-migration.md)
- [Transfer planning](../transfer-planning.md)
- [Alliance intelligence and diplomacy](../alliance-intelligence.md)
- [Security baseline](../../../security/security-baseline.md)
- [Kingdoms product/acceptance evidence](../product/README.md)
- [Kingdoms operations](../operations/README.md)
