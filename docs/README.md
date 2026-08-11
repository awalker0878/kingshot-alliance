# Kingshot Alliance documentation

This directory is the canonical documentation entry point for the Kingshot Alliance repository.

## Current program state

The product implementation baseline ends at **Phase 6**. Product Phases 0–6, repository-controlled production hardening, and accepted `KINGDOMS-001` through `KINGDOMS-003` increments are complete. Real production cutover remains **not yet approved** until the external infrastructure/operator evidence required by [production launch approval](product/production-launch-approval.md) is recorded.

The Documentation Completion Program has reached its final phase, **DCP-P7 — Maintenance automation and final acceptance**. Current phase/gate evidence is in [Documentation program status](product/documentation-program-status.md). Normal post-program documentation obligations are defined by the [Documentation maintenance standard](product/documentation-maintenance-standard.md).

## Start here

- [Current architecture and ADR index](adr/README.md) — system architecture and durable decision lifecycle.
- [Cross-domain dependency map](product/cross-domain-dependency-map.md) — consumer→owner supported collaboration across all 14 domains.
- [Shared glossary](product/glossary.md) — terminology whose ambiguity changes tenancy, ownership, integrations, evidence, or status meaning.
- [Current capability matrix](product/current-capability-matrix.md) — implemented capability and explicit current boundaries.
- [Domain documentation](domains/README.md) — canonical domain contracts and domain-owned evidence.
- [Product/program documentation](product/README.md) — baseline, DCP standards/evidence, architecture audits, historical acceptance, production decisions.
- [Documentation maintenance standard](product/documentation-maintenance-standard.md) — what documentation changes when a material code/product/architecture/operations/security/interface/evidence contract changes.
- [Definition of Done](product/definition-of-done.md) — normal accepted-change checklist.
- [Shared operations](operations/README.md) — runtime configuration, background processing, observability, deployment, recovery, release guidance.
- [Shared security](security/README.md) — cross-program security baseline, historical threat evidence, production security boundary.
- [Production launch approval](product/production-launch-approval.md) — real-production go/no-go authority.

## Ownership model

Documentation follows code ownership:

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

Each canonical domain owns its business/runtime contract plus the P1–P5 specialized living profiles:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/security/README.md
docs/domains/<domain>/operations/README.md
docs/domains/<domain>/interfaces/README.md
docs/domains/<domain>/testing/README.md
```

Domain-specific capability contracts and product/acceptance evidence also live beneath the owner where required.

Top-level groups remain deliberately shared/system-level:

- `adr/` — current architecture index and durable decisions;
- `product/` — cross-program scope/governance/status/current-state/audits/DCP/historical phase-wide evidence/production decisions;
- `security/` — shared security policy, historical phase-wide threat models, production security boundary;
- `operations/` — shared runtime/configuration/observability/deployment/recovery/runbooks and historical phase-wide operating evidence.

No parallel `docs/architecture/`, `docs/wiki/`, top-level `docs/runbooks/`, or flat domain living-document tree is canonical.

## Reader paths

### Developer / architect

1. Read [current architecture](adr/README.md).
2. Use the [dependency map](product/cross-domain-dependency-map.md) to identify supported owner direction.
3. Use the [capability matrix](product/current-capability-matrix.md) to distinguish implemented/accepted behavior from explicit non-capability.
4. Open the owning domain under [domains](domains/README.md).
5. Read applicable security/operations/interfaces/testing profiles before changing a material boundary.
6. Apply [documentation maintenance](product/documentation-maintenance-standard.md) to the material change.
7. Use an ADR for durable architecture change rather than silently redefining architecture in feature documentation.

### Security reviewer

1. Start with [shared security](security/README.md).
2. Read [current architecture](adr/README.md) and [dependency map](product/cross-domain-dependency-map.md).
3. Open the owning domain's security profile/reviews.
4. Apply maintenance obligations if a trust/privacy/authorization/retention boundary changed.
5. Use [production launch approval](product/production-launch-approval.md) for repository evidence versus external production controls.

### Production operator

1. Start with [shared operations](operations/README.md).
2. Review runtime configuration, observability, and background processing.
3. Use the owning domain's operations profile for domain-specific state/diagnosis.
4. Follow shared deployment/rollback/backup/incident runbooks.
5. Check [production launch approval](product/production-launch-approval.md) before treating the service as production-approved.

A green CI/staging/recovery gate or accepted domain increment does not by itself prove real production infrastructure/operator controls.

## Documentation map

| Area | Purpose |
| --- | --- |
| [`adr/`](adr/README.md) | Current system architecture and durable architecture decisions. |
| [`domains/`](domains/README.md) | Current code/domain ownership, behavior, security, operations, interfaces, testing/evidence, and domain-specific acceptance. |
| [`operations/`](operations/README.md) | Shared runtime operations, configuration, deployment, observability, recovery, runbooks, historical operating evidence. |
| [`product/`](product/README.md) | Program baseline/status/governance, DCP standards/evidence, maintenance, dependency/glossary/capability navigation, audits, historical acceptance, production decisions. |
| [`security/`](security/README.md) | Shared security baseline, historical threat evidence, production-launch security boundary. |

These five directories are the only canonical top-level documentation groups.

## Source-of-truth rules

Use the narrowest owner:

1. accepted implementation-plan baseline and approved named scopes define approved product scope;
2. accepted ADRs define durable architecture decisions;
3. code/tests define exact implemented runtime structure/behavior;
4. living domain/capability contracts define current business/runtime ownership;
5. specialized domain profiles define current security/operations/interfaces/testing views;
6. system-level capability/dependency/audit navigation summarizes without overriding owners;
7. accepted phase/increment/DCP records prove historical acceptance at their recorded revision.

Historical evidence remains historical. Do not silently turn old phase language/test counts into current runtime truth.

## Documentation conventions and maintenance

- Follow the [Repository documentation standard](product/documentation-standard.md).
- Follow the [Documentation maintenance standard](product/documentation-maintenance-standard.md) for normal change-driven updates.
- Follow the [Documentation completeness standard](product/documentation-completeness-standard.md) for DCP or any future explicit completeness gate.
- Use lowercase kebab-case for descriptive Markdown filenames; `README.md` and numbered ADR filenames are accepted exceptions.
- Prefer one authoritative owner plus links over duplicated explanation.
- Preserve accepted historical evidence identity.
- Keep repository acceptance separate from real-production approval.
- Never commit secrets, credentials, recovery material, private endpoint details, or sensitive production incident payloads.

## Program history and continuation

Historical product Phase 0–6 evidence is indexed under [product/program documentation](product/README.md). Accepted post-baseline Kingdoms work is indexed under the [Kingdoms domain](domains/kingdoms/README.md).

`DCP-P0` through `DCP-P7` are documentation-governance phases, separate from product Phase 0–6. There is no `DCP-P8`; after P7's protected final acceptance gate, future documentation work follows the maintenance standard and Definition of Done.
