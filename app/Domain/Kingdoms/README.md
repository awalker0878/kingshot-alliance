# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, game-side Alliance intelligence/diplomacy, accepted `KINGDOMS-004` automated-ingestion services, and the `KINGDOMS-005` directional sharing-consent foundation.

`KINGDOMS-001` through `KINGDOMS-004` are accepted. K5 is in progress: P1 adds only invitation/agreement consent state and mutations. There is still no shared target or recipient observation/current/history read path.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references; tenant roster/transfer/intelligence/ingestion; and K5 consent metadata.
- `Actions/` — roster, observation, import, transfer, diplomacy/contact, K4 ingestion/operations, and K5 invitation/accept/decline/revoke/leave mutations.
- `Jobs/` — isolated per-subscription K4 acquisition work only; K5-P1 has no jobs.
- `Queries/` — tenant-first existing Kingdoms projections; K5-P1 has no shared-data query.
- `Services/`, `Contracts/`, `Data/`, and `ValueObjects/` — existing intelligence/K4 contracts plus hash-only K5 invitation token issuance and one-time issued-token return value.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies including K5 pending/active/declined/revoked consent state.
- `Http/` — first-party Kingdoms boundaries including password-confirmed K5 consent mutations.

## Public contracts

Intentional cross-domain contracts include canonical `Kingdom` references; member-safe Kingdoms reads under `alliance.view`; management actions under `kingdoms.manage`; Audit/Platform internal evidence; and `kingdoms.*` internal events that are **not** public webhook contracts.

K5-P1 introduces no public contract. Invitation creation/acceptance/decline/revoke/leave are authenticated first-party mutations only. The token is a one-time human consent bootstrap secret, not an API credential.

Stable neutral identity never grants K5 access. K5 acceptance requires a different recipient Alliance and matching current/captured Kingdom. Source observations remain source-owned and no recipient copy exists in P1.

## Dependencies

- `Alliances` — active tenant/current Kingdom plus K5 source/recipient identities.
- `Memberships` — optional existing roster/coordinator references only.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage`.
- `Identity` — human actor identity and recent-password assurance.
- `Audit` — attributable/safe consent evidence; source-side acceptance avoids cross-tenant recipient-manager actor disclosure.
- `Platform` — transactional outbox and shared runtime infrastructure.
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
- [Product and acceptance evidence](../../../docs/domains/kingdoms/product/README.md)
- [Security evidence](../../../docs/domains/kingdoms/security/README.md)
- [Operations](../../../docs/domains/kingdoms/operations/README.md)
- [Interfaces](../../../docs/domains/kingdoms/interfaces/README.md)
- [Testing/evidence](../../../docs/domains/kingdoms/testing/README.md)

Kingdoms-specific evidence remains with the Kingdoms domain. Top-level product/security/operations documentation is reserved for program-wide/shared concerns.