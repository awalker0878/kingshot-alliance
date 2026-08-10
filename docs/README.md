# Kingshot Alliance documentation

This directory is the canonical documentation entry point for the Kingshot Alliance repository.

## Current program state

The baseline implementation plan ends at **Phase 6**. Phases 0–6 and repository-controlled production hardening are complete and accepted. `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` are accepted post-program product increments. A real production cutover remains **not yet approved** until the external infrastructure/operational evidence required by [production launch approval](product/production-launch-approval.md) is recorded.

Start with:

- [Current capability matrix](product/current-capability-matrix.md) — present-tense implemented capabilities and explicit boundaries.
- [Domain documentation](domains/README.md) — canonical one-folder-per-code-domain living contracts.
- [Documentation standard](product/documentation-standard.md) — required docs structure, naming, formats, ownership, and CI enforcement.
- [Architecture decisions/current architecture](adr/README.md) — current system map and accepted ADRs.
- [Implementation plan](product/implementation-plan.md) — authoritative completed Phase 0–6 baseline and canonical repository structure.
- [Production launch approval](product/production-launch-approval.md) — authoritative real-production go/no-go record.
- [Operations](operations/README.md) — runtime configuration, background processing, observability, deployment, recovery, and release guidance.
- [Security](security/README.md) — security baseline, current reviews, and historical threat evidence.

## Reader paths

### Developer

1. Read the [architecture view](adr/README.md).
2. Use the [capability matrix](product/current-capability-matrix.md) to distinguish current capability from explicit non-capability.
3. Open the owning canonical domain folder under [`domains/`](domains/README.md). The mapping is deterministic: `app/Domain/<Domain>/` ↔ `docs/domains/<domain>/README.md`.
4. Read the approved product-increment scope/implementation plan when changing accepted post-program capability.
5. Review [security](security/README.md) and [operations](operations/README.md) impacts.
6. Update code, tests, living docs, security/operations evidence, and product status together when behavior materially changes.

For a material architecture decision, add/update an ADR rather than silently redefining architecture in feature documentation.

### Alliance/operator

Use the relevant living domain contract:

- [Alliances](domains/alliances/README.md) — Alliance aggregate/settings and active-Alliance context.
- [Identity](domains/identity/README.md) — authentication, verified email, password/session security, and MFA.
- [Memberships](domains/memberships/README.md) — membership/invitation lifecycle.
- [Authorization](domains/authorization/README.md) — roles, permissions, and RBAC.
- [Content](domains/content/README.md) — public/member content, revisions, publication, and media.
- [Events](domains/events/README.md) — scheduling, recurrence, registration, attendance, CSV/ICS behavior.
- [Rallies](domains/rallies/README.md) — guidance, formations, groups, assignments, and Rally participation.
- [Recruitment](domains/recruitment/README.md) — application/candidate/onboarding/retention workflow.
- [Contributions](domains/contributions/README.md) — contribution records/calculations/reporting/exports.
- [Notifications](domains/notifications/README.md) — durable reminder/report-request coordination.
- [Integrations](domains/integrations/README.md) — read-only API and signed webhooks.
- [Kingdoms](domains/kingdoms/README.md) — accepted K1–K3 Kingdom/player/roster/transfer/Alliance-intelligence capabilities.
- [Platform](domains/platform/README.md) — cross-tenant platform administration/lifecycle/entitlements/retention.

### Security reviewer

1. [Security baseline](security/security-baseline.md).
2. [Architecture view](adr/README.md).
3. [Identity](domains/identity/README.md), [Alliances](domains/alliances/README.md), [Memberships](domains/memberships/README.md), and [Authorization](domains/authorization/README.md) for identity/tenancy/RBAC.
4. [Integrations](domains/integrations/README.md) for credential/webhook/SSRF boundaries.
5. [Kingdoms](domains/kingdoms/README.md) plus K1/K2/K3 security reviews for game identity, tenant intelligence, transfer, diplomacy, and privacy boundaries.
6. [Production launch security review](security/production-launch-security-review.md) and [production launch approval](product/production-launch-approval.md) for repository evidence versus still-external production controls.

### Production operator

1. [Operations index](operations/README.md).
2. [Runtime configuration](operations/configuration-reference.md).
3. [Deployment runbook](operations/runbooks/deployment.md) and [release checklist](operations/release-checklist.md).
4. [Observability](operations/observability.md).
5. [Background processing](operations/background-processing.md).
6. Domain-specific operating guides, including accepted Kingdoms operations where relevant.
7. [Rollback](operations/runbooks/rollback.md), [backup/restore](operations/runbooks/backup-restore.md), and [incident response](operations/runbooks/incident-response.md).
8. [Production launch approval](product/production-launch-approval.md) before treating the service as production-approved.

A green CI/staging/recovery demonstration or accepted product increment does not by itself approve production infrastructure or operations.

## Documentation map

| Area | Purpose |
| --- | --- |
| [`adr/`](adr/README.md) | Current architecture view and durable architecture decisions. |
| [`domains/`](domains/README.md) | Current domain behavior/ownership, mirrored one-to-one from `app/Domain/*`. |
| [`operations/`](operations/README.md) | Runtime operations, configuration, deployment, recovery, and operational evidence. |
| [`product/`](product/README.md) | Baseline plan, increment scopes/plans/status/acceptance, documentation governance, architecture audits, launch approval. |
| [`security/`](security/README.md) | Security baseline, current reviews, launch-security evidence, historical threat models. |

These five directories are the only canonical top-level documentation groups. Do not add parallel `docs/wiki/`, `docs/architecture/`, or top-level `docs/runbooks/` trees.

## Domain documentation rule

Every canonical code domain has exactly one matching documentation root:

```text
app/Domain/<CanonicalDomain>/
        ↕
docs/domains/<canonical-domain-kebab>/README.md
```

Capability detail stays inside the owning folder, for example:

```text
docs/domains/kingdoms/
  README.md
  roster.md
  snapshots.md
  intelligence.md
  csv-migration.md
  transfer-planning.md
  alliance-intelligence.md
```

`docs/domains/README.md` is the only Markdown file directly under `docs/domains/`. The parity and no-flat-file rules are enforced by `tests/Architecture/RepositoryStructureTest.php` in CI.

## Source-of-truth rules

When documents overlap:

1. `product/implementation-plan.md` defines the completed baseline and canonical repository structure.
2. Approved named increment scopes explicitly extend product scope.
3. Accepted ADRs define material architecture decisions.
4. Current status records such as `current-capability-matrix.md` and `production-launch-approval.md` define present capability/go-no-go state.
5. Domain, operations, and security docs define current contracts/operating detail.
6. Phase/increment validations, threat/security reviews, accessibility records, audits, and exit reports are evidence records for the work they describe.

Code and tests remain authoritative for exact implemented runtime behavior. Documentation drift is a defect to correct, not a compatibility state to preserve.

## Documentation conventions

- Follow the [documentation standard](product/documentation-standard.md).
- Use lowercase kebab-case for descriptive Markdown filenames; `README.md` is the directory-index exception and numbered ADR names remain accepted.
- Use repository-relative links.
- Prefer one authoritative contract over duplicated explanations.
- Keep living domain documentation under its matching domain folder.
- Keep product scope/status/evidence under `product/`, architecture decisions under `adr/`, operational procedures under `operations/`, and security evidence under `security/`.
- Record real evidence identifiers; do not mark external production controls complete because CI passed.
- Never commit secrets, credentials, recovery material, private endpoint details, or sensitive production incident payloads.

## Historical delivery and future work

For historical Phase 0–6 delivery, use the [product index](product/README.md) and relevant phase exit/evidence records. For accepted post-program work, follow the increment scope, implementation plan, living domain/security/operations contracts, and exit report.

Release notes and end-user onboarding are intentionally separate from phase/increment acceptance evidence.
