# Kingdoms domain

## Purpose

Owns approved Kingshot game-world reference identity plus Alliance-scoped roster/history/intelligence, controlled roster migration, transfer planning, game-side Alliance intelligence/diplomacy, and governed `KINGDOMS-004` automated-ingestion control/promotion/scheduler/operations services.

`KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` are accepted. K4 through P5 provides a generic empty-by-default ingestion pipeline: manager control, bounded staging/quarantine, delegated factual player/game-Alliance promotion, scheduled acquisition/cursor/retry/replay, source-revocation reconciliation, bounded operational retention and aggregate health monitoring. Production adapter configuration remains empty and no real source is approved.

## Owned code

- `Models/` — neutral Kingdom/player/game-Alliance references and Alliance-owned roster/transfer/intelligence/ingestion persistence.
- `Actions/` — explicit roster, observation, import, transfer, diplomacy/contact, ingestion-control, scheduler/orchestration/replay, source-reconciliation/retention, and delegated promotion mutations.
- `Jobs/` — isolated per-subscription K4 acquisition work only.
- `Queries/` — tenant-first roster/history/intelligence/transfer/game-Alliance/ingestion projections.
- `Services/`, `Contracts/`, and `Data/` — descriptive intelligence plus K4 adapter allowlist, normalization/acquisition, bounded page and operational-health contracts.
- `Enums/` — accepted Kingdoms lifecycle/state vocabularies.
- `Http/` — first-party Kingdoms request/presentation boundaries.

## Public contracts

Intentional cross-domain contracts include canonical `Kingdom` references consumed by Alliances; member-safe Kingdoms queries under `alliance.view`; management actions/queries under `kingdoms.manage`; optional same-Alliance Membership references without transferring ownership; and internal `kingdoms.*` domain/outbox events that are **not** public webhook contracts.

Stable game IDs scoped to one Kingdom are the only automatic neutral identity keys. Names, tags, handles, and source-local labels never auto-merge identity. Neutral `KingdomPlayer`/`KingdomAlliance` references never grant tenant access.

K4 adapter registration/acquisition is repository/operator configuration, not a tenant-configurable external integration contract. K4 promotion never creates roster/tracking relationships and machine game-Alliance observations cannot correct/invalidate history. Source removal/version drift disables future acquisition. Operational retention cannot delete promoted K1/K3 canonical history or rewrite its copied provenance.

Generic scheduling/maintenance does not create a public Kingdoms API, inbound webhook, arbitrary source endpoint, or approved production provider.

## Dependencies

- `Alliances` — active tenant context and canonical Alliance→Kingdom association.
- `Memberships` — optional roster/coordinator references; membership remains Memberships-owned.
- `Authorization` — `alliance.view`, `alliance.manage`, and `kingdoms.manage`.
- `Identity` — human actor identity and recent-password assurance; machine provenance is explicit/null-actor.
- `Audit` — attributable human and bounded machine mutation evidence.
- `Platform` — scheduler/queue and transactional-outbox infrastructure.
- `Integrations` — explicit external-exposure boundary; Kingdoms events/API surfaces remain internal/not approved for public exposure.

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
- [Interfaces](../../../docs/domains/kingdoms/interfaces/README.md)
- [Testing/evidence](../../../docs/domains/kingdoms/testing/README.md)

Kingdoms-specific evidence remains with the Kingdoms domain. Top-level product/security/operations documentation is reserved for program-wide/shared concerns.
