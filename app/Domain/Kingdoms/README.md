# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, and game-side Alliance intelligence/diplomacy capabilities delivered by accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references and Alliance-owned Kingdoms persistence.
- `Actions/` — explicit roster, observation, import, transfer, diplomacy, and contact mutations.
- `Queries/` — tenant-first roster/history/intelligence/transfer/game-Alliance projections.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies.
- `Http/` — first-party Kingdoms request/presentation boundaries.

## Public contracts

Intentional cross-domain contracts include:

- canonical `Kingdom` references consumed by Alliances;
- member-safe Kingdoms queries under `alliance.view`;
- management actions/queries under `kingdoms.manage`;
- optional same-Alliance Membership references without transferring Memberships ownership; and
- internal `kingdoms.*` domain/outbox events, which are **not** public webhook contracts.

Stable game IDs scoped to one Kingdom are the only automatic neutral identity keys. Names, tags, and handles never auto-merge identity. Neutral `KingdomPlayer`/`KingdomAlliance` references never grant cross-tenant access.

## Dependencies

- `Alliances` — active tenant context and canonical Alliance→Kingdom association.
- `Memberships` — optional roster/coordinator references; membership remains Memberships-owned.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage`.
- `Identity` — actor identity and recent-password assurance.
- `Audit` — attributable mutation evidence.
- `Platform` — transactional-outbox infrastructure.
- `Integrations` — explicit external-exposure boundary; current Kingdoms events/API surfaces remain internal/not approved for public exposure.

## Canonical documentation

- [`docs/domains/kingdoms/`](../../../docs/domains/kingdoms/README.md)
- [Roster](../../../docs/domains/kingdoms/roster.md)
- [Player snapshots](../../../docs/domains/kingdoms/snapshots.md)
- [Roster intelligence](../../../docs/domains/kingdoms/intelligence.md)
- [Controlled CSV migration](../../../docs/domains/kingdoms/csv-migration.md)
- [Transfer planning](../../../docs/domains/kingdoms/transfer-planning.md)
- [Alliance intelligence and diplomacy](../../../docs/domains/kingdoms/alliance-intelligence.md)

Acceptance evidence remains under `docs/product/` and security/operations evidence under their owning documentation groups.
