# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, game-side Alliance intelligence/diplomacy, and governed `KINGDOMS-004` automated-ingestion control/promotion services.

`KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` are accepted. K4 through P3 may promote factual player snapshots and game-Alliance observations only to existing owning-Alliance relationships after stable-ID/current-Kingdom/source checks. It still has no real source or scheduler/worker; production adapter configuration remains empty.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references and Alliance-owned roster/transfer/intelligence/ingestion persistence.
- `Actions/` — explicit roster, observation, import, transfer, diplomacy/contact, ingestion-control, and delegated promotion mutations.
- `Queries/` — tenant-first roster/history/intelligence/transfer/game-Alliance/ingestion projections.
- `Services/` and `Contracts/` — descriptive intelligence plus K4 adapter allowlist/normalization contracts.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies.
- `Http/` — first-party Kingdoms request/presentation boundaries.

## Public contracts

Intentional cross-domain contracts include canonical `Kingdom` references consumed by Alliances; member-safe Kingdoms queries under `alliance.view`; management actions/queries under `kingdoms.manage`; optional same-Alliance Membership references without transferring ownership; and internal `kingdoms.*` domain/outbox events that are **not** public webhook contracts.

Stable game IDs scoped to one Kingdom are the only automatic neutral identity keys. Names, tags, handles, and source-local labels never auto-merge identity. Neutral `KingdomPlayer`/`KingdomAlliance` references never grant tenant access.

K4 adapter registration is repository/operator configuration, not a tenant-configurable external integration contract. K4 promotion never creates roster/tracking relationships and machine game-Alliance observations cannot correct/invalidate history. No public Kingdoms ingestion API or inbound webhook exists.

## Dependencies

- `Alliances` — active tenant context and canonical Alliance→Kingdom association.
- `Memberships` — optional roster/coordinator references; membership remains Memberships-owned.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage`.
- `Identity` — human actor identity and recent-password assurance; machine provenance is explicit/null-actor.
- `Audit` — attributable human and bounded machine mutation evidence.
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
- [Automated game-data ingestion](../../../docs/domains/kingdoms/automated-ingestion.md)
- [Product and acceptance evidence](../../../docs/domains/kingdoms/product/README.md)
- [Security evidence](../../../docs/domains/kingdoms/security/README.md)
- [Operations](../../../docs/domains/kingdoms/operations/README.md)

Kingdoms-specific evidence remains with the Kingdoms domain. Top-level product/security/operations documentation is reserved for program-wide/shared concerns.
