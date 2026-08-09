# Domain documentation

[← Documentation home](../README.md)

This directory documents business/domain behavior and ownership. Runtime code remains domain-first under `app/Domain/<CanonicalDomain>`; these documents explain contracts/workflows too broad to express clearly in code alone.

## Current guides

- [Identity, tenancy, and membership](identity-tenancy-and-membership.md) — global identity, active-alliance context, membership lifecycle, invitations, roles, permissions, and RBAC.
- [Content management](content-management.md) — alliance public presence, authored content, visibility, revisions, media, and management behavior.
- [Events and rallies](events-and-rallies.md) — events, recurrence, registration, attendance, reminders, rally guidance, formations, and participation.
- [Recruitment](recruitment.md) — application modes, candidate pipeline, review, decisions, conversion, metrics, and retention.
- [Contributions and reporting](contributions-and-reporting.md) — contribution records, calculation semantics, corrections, reporting, exports, and data quality.
- [Notifications](notifications.md) — event-reminder delivery state, scheduled-report coordination, idempotency, scheduler flow, and recovery.
- [Integrations](integrations.md) — API credential/authentication contract, read-only API endpoints, webhook signing/delivery contract, and integration boundaries.
- [Kingdoms](kingdoms.md) — accepted `KINGDOMS-001` + `KINGDOMS-002` ownership map across global Kingdom/game-player references and alliance-owned roster/transfer workflows.
- [Kingdoms roster](kingdoms-roster.md) — game-player identity, alliance-owned roster, membership linkage, authorization, filtering, data minimization, and tenant isolation.
- [Kingdoms player snapshots](kingdoms-snapshots.md) — append-only observations, idempotency, latest projection, snapshot freshness, history visibility and provenance.
- [Kingdoms roster intelligence](kingdoms-intelligence.md) — exact power aggregates, data-quality indicators, bounded 7/30-day trends, linkage/movement summaries and manager-only comparisons.
- [Kingdoms controlled CSV migration](kingdoms-csv-migration.md) — strict CSV schema, dry-run classification, identity resolution, atomic/idempotent confirmation, provenance and export safety.
- [Kingdoms transfer planning](kingdoms-transfer-planning.md) — transfer cycles, participant intent/destinations, groups/coordinators, manual readiness/blockers, explicit completion/roster handoff and privacy/tenant boundaries.
- [Platform scale and administration](platform-scale-and-administration.md) — platform administration, tenant lifecycle, entitlements, API/webhook controls, retention, and operational scale.

`KINGDOMS-001` and `KINGDOMS-002` are **Accepted** end-to-end increments. See the [KINGDOMS-001 exit report](../product/kingdoms-roster-intelligence-exit-report.md) and [KINGDOMS-002 exit report](../product/kingdoms-transfer-planning-exit-report.md).

## Architecture evidence

- [Repository/domain structure audit](repository-structure-audit.md) — evidence for the canonical domain-first physical layout.
- [Domain boundary audit](domain-boundary-audit.md) — evidence for semantic ownership and intentional cross-domain contracts.

These audits explain how the current structure was validated; the [implementation plan](../product/implementation-plan.md), approved post-program increment scopes, and accepted ADRs remain the normative architecture/scope sources.

## Canonical domain roots

The implementation plan defines these canonical ownership roots:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

All canonical roots own runtime PHP. `Kingdoms` owns first-class Kingdom and neutral player references plus alliance-scoped roster, snapshots, controlled CSV migration, derived intelligence and accepted transfer planning. Global Kingdom/player references never replace the Alliance tenant boundary.

A domain does not require a separate Markdown file merely because it has runtime code. Add a domain guide when it clarifies a meaningful workflow, public contract, lifecycle, or cross-domain boundary that would otherwise be duplicated across implementation files.

## Boundary rules

- A guide describes behavior owned by its domain; it should not create an alternate architecture.
- Cross-domain collaboration should reference intentional public actions, queries, services, value objects, or events rather than another domain's persistence internals.
- Global platform/foundation concerns may be referenced where necessary, but domain-specific persistence remains owned by the domain that defines it.
- Historical phase ownership and approved product-increment scope belong in `../product/`; threat and abuse analysis belongs in `../security/`; operational procedures belong in `../operations/`.
- If a domain guide conflicts with the baseline implementation plan, an approved product-increment scope, or an accepted ADR, update the guide or record the required scope/architecture decision rather than silently redefining the architecture here.

## Updating a domain guide

When behavior changes materially, update the guide together with its tests and any affected security, operations, accessibility, capability, or acceptance evidence. Prefer stable business rules and invariants over controller-by-controller detail.
