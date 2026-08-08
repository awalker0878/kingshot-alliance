# Architecture decision records

[← Documentation home](../README.md)

Kingshot Alliance is an enterprise modular monolith organized by explicit business domains. The canonical physical repository structure is defined by the [implementation plan](../product/IMPLEMENTATION_PLAN.md) and ADR 0008.

## Decision records

- [ADR 0001 — Modular monolith](0001-modular-monolith.md)
- [ADR 0002 — Alliance-level tenancy](0002-alliance-level-tenancy.md)
- [ADR 0003 — First-party authentication](0003-first-party-authentication.md)
- [ADR 0004 — Queues and transactional outbox](0004-queues-and-transactional-outbox.md)
- [ADR 0005 — S3-compatible object storage](0005-s3-compatible-object-storage.md)
- [ADR 0006 — Observability and correlation](0006-observability-and-correlation.md)
- [ADR 0007 — Testing toolchain compatibility](0007-testing-toolchain-compatibility.md)
- [ADR 0008 — Domain-first source layout](0008-domain-first-source-layout.md)

Use [the ADR template](adr-template.md) for new material decisions.

## ADR lifecycle

Use an ADR for a material architecture decision whose rationale and consequences should survive individual PRs. Prefer updating operational/domain documentation for implementation detail that does not change architecture.

A new ADR should clearly identify its status and relationship to earlier decisions. If a decision replaces an older ADR, mark the older record superseded rather than silently rewriting the historical rationale. The implementation plan remains authoritative for approved product scope and the canonical repository groups; an ADR may refine that architecture but must not silently expand program scope.

## Canonical source structure

```text
app/
  Domain/
    Alliances/
    Audit/
    Authorization/
    Content/
    Contributions/
    Events/
    Identity/
    Integrations/
    Kingdoms/
    Memberships/
    Notifications/
    Platform/
    Rallies/
    Recruitment/
docs/
  adr/
  domains/
  operations/
  product/
  security/
resources/js/
tests/
  Architecture/
  Feature/
  Integration/
  Performance/
  TenantIsolation/
  Unit/
```

Runtime PHP is owned by a canonical `app/Domain/<Domain>` module. Internal organization such as `Actions`, `Queries`, `Services`, `Models`, `Http`, `Enums`, and `ValueObjects` lives inside the owning domain rather than in parallel top-level application layers.

Domains should communicate through intentional public actions, queries, services, value objects, or events. A cross-domain dependency must be part of the other domain's supported contract rather than an accidental dependency on its persistence internals.

Architecture documentation must not introduce additional top-level `app/`, `docs/`, or `tests/` groupings that conflict with the implementation plan and repository-structure tests.
