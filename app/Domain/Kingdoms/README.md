# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, game-side Alliance intelligence/diplomacy, accepted `KINGDOMS-004` automated-ingestion services, and `KINGDOMS-005` directional sharing through K5-P3.

`KINGDOMS-001` through `KINGDOMS-004` are accepted. K5 is in progress: P1 provides invitation/agreement consent; P2 adds explicit source target grants/removal and bounded safe current facts; P3 adds bounded accepted source history for one explicit active target. Complete source/recipient sharing pages remain P4 work.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references; tenant roster/transfer/intelligence/ingestion; K5 consent and explicit grant metadata.
- `Actions/` — roster, observation, import, transfer, diplomacy/contact, K4 ingestion/operations, K5 consent lifecycle, target grant/removal, and fail-closed sharing invalidation on supported Kingdom drift.
- `Jobs/` — isolated per-subscription K4 acquisition work only; K5-P3 has no jobs.
- `Queries/` — tenant-first Kingdoms projections including bounded recipient-safe `SharedKingdomIntelligenceCurrentQuery` and `SharedKingdomIntelligenceHistoryQuery`.
- `Services/`, `Contracts/`, `Data/`, and `ValueObjects/` — existing intelligence/K4 contracts plus hash-only K5 invitation token issuance, one-time issued-token return value, and encrypted target-bound shared-history continuation cursor.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies including K5 agreement and target grant states.
- `Http/` — first-party Kingdoms boundaries including password-confirmed K5 consent and target mutations. No K5 public/current/history data API or complete sharing page exists yet.

## Public contracts

Intentional cross-domain contracts include canonical `Kingdom` references; member-safe Kingdoms reads under `alliance.view`; management actions under `kingdoms.manage`; Audit/Platform internal evidence; and `kingdoms.*` internal events that are **not** public webhook contracts.

K5 introduces no public contract. Consent and target changes are authenticated first-party mutations. P2/P3 current/history facts are internal bounded queries over explicitly granted source-owned targets.

Stable neutral identity never grants K5 access. Recipient current/history reads require active recipient → active directional agreement → active explicit grant → live valid source tracking/context, and every history page repeats authorization.

History continuation is encrypted, target-bound, fixed to one internal `asOf` snapshot, capped to 50 rows/page and 250 accepted observations per traversal. It is not a reusable authorization credential. P4 must not expose arbitrary client-controlled historical `asOf` windows without separate review.

Source observations remain source-owned; K5 creates no recipient canonical tracking/observation copy. Received current/history intelligence cannot be used as the upstream tracking target of another K5 share.

## Dependencies

- `Alliances` — active tenant/current Kingdom, K5 source/recipient identities, and the supported Kingdom-change lifecycle that terminalizes affected K5 agreements.
- `Memberships` — optional existing roster/coordinator references only.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage`.
- `Identity` — human actor identity and recent-password assurance.
- `Audit` — attributable/safe consent/grant evidence; counterpart records avoid cross-tenant manager identity disclosure where appropriate.
- `Platform` — transactional outbox, encryption and shared runtime infrastructure.
- `Integrations` — external-exposure boundary; all K5 events remain internal/not public-webhook eligible.

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
- [Product and acceptance evidence](../../../docs/domains/kingdoms/product/README.md)
- [Security evidence](../../../docs/domains/kingdoms/security/README.md)
- [Operations](../../../docs/domains/kingdoms/operations/README.md)
- [Interfaces](../../../docs/domains/kingdoms/interfaces/README.md)
- [Testing/evidence](../../../docs/domains/kingdoms/testing/README.md)

Kingdoms-specific evidence remains with the Kingdoms domain. Top-level product/security/operations documentation is reserved for program-wide/shared concerns.
