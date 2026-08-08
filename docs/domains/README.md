# Domain documentation

[← Documentation home](../README.md)

This directory documents business/domain behavior and ownership. Runtime code remains domain-first under `app/Domain/<CanonicalDomain>`; these documents explain the contracts and workflows that are too broad to express clearly in code alone.

## Current guides

- [Identity, tenancy, and membership](identity-tenancy-and-membership.md) — global identity, active-alliance context, membership lifecycle, invitations, roles, permissions, and RBAC.
- [Content management](content-management.md) — alliance public presence, authored content, visibility, revisions, media, and management behavior.
- [Events and rallies](events-and-rallies.md) — events, recurrence, registration, attendance, reminders, rally guidance, formations, and participation.
- [Recruitment](recruitment.md) — application modes, candidate pipeline, review, decisions, conversion, metrics, and retention.
- [Contributions and reporting](contributions-and-reporting.md) — contribution records, calculation semantics, corrections, reporting, exports, and data quality.
- [Notifications](notifications.md) — event-reminder delivery state, scheduled-report coordination, idempotency, scheduler flow, and recovery.
- [Integrations](integrations.md) — API credential/authentication contract, read-only API endpoints, webhook signing/delivery contract, and integration boundaries.
- [Kingdoms](kingdoms.md) — first-class global Kingdom references, alliance Kingdom association, migration behavior, authorization, audit/outbox, and the validated Slice A foundation.
- [Kingdoms roster](kingdoms-roster.md) — Slice B game-player identity, alliance-owned roster, membership linkage, roster authorization, filtering, data minimization, and tenant-isolation validated-candidate contract.
- [Kingdoms player snapshots](kingdoms-snapshots.md) — Slice C1 append-only observations, idempotency, latest projection, snapshot freshness, history visibility, provenance, and tenant-isolation validated-candidate contract.
- [Platform scale and administration](platform-scale-and-administration.md) — platform administration, tenant lifecycle, entitlements, API/webhook controls, retention, and operational scale.

The Kingdom foundation from `KINGDOMS-001` Slice A / `K1-P1` is validated. Slice B / `K1-P2` and Slice C1 / `K1-P3` have passed their protected implementation gates and remain review candidates until accepted into the dependency stack. Roster intelligence/trends and CSV workflows remain later approved phases.

## Architecture evidence

- [Repository/domain structure audit](repository-structure-audit.md) — evidence for the canonical domain-first physical layout.
- [Domain boundary audit](domain-boundary-audit.md) — evidence for semantic ownership and intentional cross-domain contracts.

These audits explain how the current structure was validated; the [implementation plan](../product/implementation-plan.md), approved post-program increment scopes, and accepted ADRs remain the normative architecture/scope sources.

## Canonical domain roots

The implementation plan defines these canonical ownership roots:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, and `Recruitment`.

All canonical roots own runtime PHP. `Kingdoms` owns the validated first-class Kingdom foundation plus the validated Slice B roster and Slice C1 snapshot candidates on this branch. Later `KINGDOMS-001` capabilities remain approved scope rather than current accepted behavior until their own implementation phases pass their gates and are accepted into the dependency stack.

A domain does not require a separate Markdown file merely because it has runtime code. Add a domain guide when it clarifies a meaningful workflow, public contract, lifecycle, or cross-domain boundary that would otherwise be duplicated across implementation files.

## Boundary rules

- A guide describes behavior owned by its domain; it should not create an alternate architecture.
- Cross-domain collaboration should reference intentional public actions, queries, services, value objects, or events rather than another domain's persistence internals.
- Global platform/foundation concerns may be referenced where necessary, but domain-specific persistence remains owned by the domain that defines it.
- Historical phase ownership and approved product-increment scope belong in `../product/`; threat and abuse analysis belongs in `../security/`; operational procedures belong in `../operations/`.
- If a domain guide conflicts with the baseline implementation plan, an approved product-increment scope, or an accepted ADR, update the guide or record the required scope/architecture decision rather than silently redefining the architecture here.

## Updating a domain guide

When behavior changes materially, update the guide together with its tests and any affected security, operations, accessibility, capability, or acceptance evidence. Prefer describing stable business rules and invariants over controller-by-controller implementation detail.
