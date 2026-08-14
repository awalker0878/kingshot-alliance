# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, game-side Alliance intelligence/diplomacy, accepted `KINGDOMS-004` automated-ingestion services, and accepted `KINGDOMS-005` directional shared intelligence.

`KINGDOMS-001` through `KINGDOMS-005` are accepted. K5 P1 provides invitation/agreement consent; P2 adds explicit source target grants/removal and bounded safe current facts; P3 adds bounded accepted source history for one explicit active target; P4 adds complete first-party member/manager sharing presentation plus immediate terminal invitation-hash erasure; P5 adds bounded scheduled retention of eligible old operational sharing rows and realistic-volume current/history capacity evidence; P6 re-proves the complete cross-tenant seam without adding new runtime behavior.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references; tenant roster/transfer/intelligence/ingestion; K5 consent and explicit grant metadata.
- `Actions/` — roster, observation, import, transfer, diplomacy/contact, K4 ingestion/operations, K5 consent lifecycle, target grant/removal, one-time invitation-hash erasure on terminal/consumed paths, fail-closed sharing invalidation on supported Kingdom drift, and bounded `EnforceKingdomIntelligenceSharingRetention` operational cleanup.
- `Jobs/` — isolated per-subscription K4 acquisition work only; K5 adds no jobs.
- `Queries/` — tenant-first Kingdoms projections including bounded recipient-safe `SharedKingdomIntelligenceCurrentQuery`, `SharedKingdomIntelligenceHistoryQuery`, and bounded manager `KingdomIntelligenceSharingManageQuery`.
- `Services/`, `Contracts/`, `Data/`, and `ValueObjects/` — existing intelligence/K4 contracts plus hash-only pending K5 invitation token issuance, one-time issued-token return value, and encrypted target-bound shared-history continuation cursor.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies including K5 agreement and target grant states.
- `Http/` — first-party Kingdoms boundaries including K5 consent/target mutations plus authenticated member-safe shared-facts/history and manager-only sharing-management pages. No K5 public data API/webhook exists.

## Public contracts

Intentional cross-domain contracts include canonical `Kingdom` references; member-safe Kingdoms reads under `alliance.view`; management actions under `kingdoms.manage`; Audit/Platform internal evidence; and `kingdoms.*` internal events that are **not** public webhook contracts.

K5 introduces no public contract. Consent and target changes are authenticated first-party mutations. Current/history facts are bounded source-owned projections exposed only through authenticated first-party presentation. P5 retention is an internal Artisan/scheduler surface, not a public or tenant-facing data API. P6 adds acceptance evidence only.

Stable neutral identity never grants K5 access. Recipient current/history reads require active recipient → active directional agreement → active explicit grant → live valid source tracking/context, and every history page repeats authorization.

History continuation is encrypted, target-bound, fixed to one internal `asOf` snapshot, capped to 50 rows/page and 250 accepted observations per traversal. It is not a reusable authorization credential. The first-party page exposes no arbitrary client-controlled historical `asOf` window.

Invitation plaintext exists only in the authenticated creation response/component memory and is never an Inertia/session prop. Pending invitation hashes are persisted only while needed; accept, decline and revoke erase them. The current forward schema migration allows terminal null hashes without rewriting accepted P1 history.

P5 retention uses one total work budget clamped to 1–2000 and may delete only eligible old pending/terminal/removed K5 operational rows. Active shares/grants, source tracking/observations, Audit events and outbox messages remain outside the cleanup boundary. Candidate eligibility is rechecked in the delete statement.

Source observations remain source-owned; K5 creates no recipient canonical tracking/observation copy. Received current/history intelligence cannot be used as the upstream tracking target of another K5 share.

## Dependencies

- `Alliances` — active tenant/current Kingdom, K5 source/recipient identities, and the supported Kingdom-change lifecycle that terminalizes affected K5 agreements.
- `Memberships` — optional existing roster/coordinator references only.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage` for Alliance-owned workflows; EVENTS-002 consumes the same `Kingdom` identity through separate exact-Kingdom role assignments.
- `Identity` — human actor identity and recent-password assurance.
- `Audit` — attributable/safe consent/grant evidence; counterpart records avoid cross-tenant manager identity disclosure where appropriate.
- `Platform` — transactional outbox, encryption and shared runtime infrastructure.
- `Integrations` — external-exposure boundary; all K5 events remain internal/not public-webhook eligible.
- `PostgreSQL / scheduler runtime` — K5 retention state and the daily bounded maintenance command; no external provider/credential is added by K5.

## Canonical documentation

- [`docs/domains/kingdoms/`](../../../docs/domains/kingdoms/README.md)
- [Roster](../../../docs/domains/kingdoms/roster.md)
- [Player snapshots](../../../docs/domains/kingdoms/snapshots.md)
- [Roster intelligence](../../../docs/domains/kingdoms/intelligence.md)
- [Controlled CSV migration](../../../docs/domains/kingdoms/csv-migration.md)
- [Transfer planning](../../../docs/domains/kingdoms/transfer-planning.md)
- [Alliance intelligence and diplomacy](../../../docs/domains/kingdoms/alliance-intelligence.md)
- [Automated game-data ingestion](../../../docs/domains/kingdoms/automated-ingestion.md)
- [Opt-in shared Kingdom intelligence](../../../docs/domains/kingdoms/shared-intelligence.md)
- [K5 Slice A validation](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-slice-a-validation.md)
- [K5 Slice A security review](../../../docs/domains/kingdoms/security/kingdoms-shared-intelligence-foundation-security-review.md)
- [K5 Slice B validation](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-slice-b-validation.md)
- [K5 Slice B security review](../../../docs/domains/kingdoms/security/kingdoms-shared-intelligence-current-facts-security-review.md)
- [K5 Slice C validation](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-slice-c-validation.md)
- [K5 Slice C security review](../../../docs/domains/kingdoms/security/kingdoms-shared-intelligence-history-security-review.md)
- [K5 Slice D validation](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-slice-d-validation.md)
- [K5 Slice D presentation security review](../../../docs/domains/kingdoms/security/kingdoms-shared-intelligence-presentation-security-review.md)
- [K5 Slice E validation](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-slice-e-validation.md)
- [K5 Slice E retention security review](../../../docs/domains/kingdoms/security/kingdoms-shared-intelligence-retention-security-review.md)
- [K5 Slice E retention operations](../../../docs/domains/kingdoms/operations/kingdoms-shared-intelligence-retention.md)
- [K5 whole-increment exit report](../../../docs/domains/kingdoms/product/kingdoms-shared-intelligence-exit-report.md)
- [Product and acceptance evidence](../../../docs/domains/kingdoms/product/README.md)
- [Security evidence](../../../docs/domains/kingdoms/security/README.md)
- [Operations](../../../docs/domains/kingdoms/operations/README.md)
- [Interfaces](../../../docs/domains/kingdoms/interfaces/README.md)
- [Testing/evidence](../../../docs/domains/kingdoms/testing/README.md)

Kingdoms-specific evidence remains with the Kingdoms domain. Top-level product/security/operations documentation is reserved for program-wide/shared concerns.
