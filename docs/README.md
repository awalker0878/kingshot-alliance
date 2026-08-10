# Kingshot Alliance documentation

This directory is the canonical documentation entry point for the Kingshot Alliance repository.

## Current program state

The baseline implementation plan ends at **Phase 6**. Phases 0–6 and repository-controlled production hardening are complete and accepted. `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` are accepted post-program domain increments. A real production cutover remains **not yet approved** until the external infrastructure/operational evidence required by [production launch approval](product/production-launch-approval.md) is recorded.

Start with:

- [Current capability matrix](product/current-capability-matrix.md) — present-tense implemented capabilities and explicit boundaries.
- [Domain documentation](domains/README.md) — canonical one-folder-per-code-domain contracts and domain-owned evidence.
- [Documentation standard](product/documentation-standard.md) — required ownership, structure, naming, formats, and CI enforcement.
- [Architecture decisions/current architecture](adr/README.md) — current system map and accepted ADRs.
- [Implementation plan](product/implementation-plan.md) — authoritative completed Phase 0–6 baseline and canonical repository structure.
- [Production launch approval](product/production-launch-approval.md) — authoritative real-production go/no-go record.
- [Shared operations](operations/README.md) — runtime configuration, background processing, observability, deployment, recovery, and release guidance.
- [Shared security](security/README.md) — repository-wide security baseline, historical phase threat evidence, and production-launch security boundary.

## Ownership model

Documentation follows code ownership.

```text
app/Domain/<Domain>/
        ↕
docs/domains/<domain>/README.md
```

A domain folder owns its living business/runtime contract and may also own domain-specific:

- capability contracts;
- product scope/implementation/validation/accessibility/acceptance evidence;
- security reviews; and
- operational diagnostics.

Top-level `product/`, `security/`, and `operations/` are deliberately broader:

- `product/` — program baseline, current status/governance, architecture audits, historical phase-wide evidence, hardening and production approval;
- `security/` — shared security policy, historical phase-wide threat models and production-launch security evidence;
- `operations/` — shared runtime configuration, scheduler/queues/outbox, observability, deployment/release/recovery runbooks and phase-wide operating evidence.

This keeps implementation documentation next to the code/business owner while preventing the shared folders from becoming flat inventories of every domain's files.

## Reader paths

### Developer

1. Read the [architecture view](adr/README.md).
2. Use the [capability matrix](product/current-capability-matrix.md) to distinguish current capability from explicit non-capability.
3. Open the owning canonical domain folder under [`domains/`](domains/README.md).
4. Read that domain's capability/product/security/operations evidence when changing domain-owned behavior.
5. Read top-level product/security/operations only for program-wide or shared-platform constraints.
6. Update code, tests, living docs, affected domain evidence, and program status together when behavior materially changes.

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
- [Kingdoms](domains/kingdoms/README.md) — accepted K1–K3 Kingdom/player/roster/transfer/Alliance-intelligence capabilities and their product/security/operations evidence.
- [Platform](domains/platform/README.md) — cross-tenant platform administration/lifecycle/entitlements/retention.

### Security reviewer

1. Start with the shared [security baseline](security/security-baseline.md).
2. Read the [architecture view](adr/README.md).
3. Open the owning domain contract and its domain-local `security/` evidence when reviewing a domain-specific capability.
4. For Kingdoms, use [Kingdoms security evidence](domains/kingdoms/security/README.md).
5. Use the [production launch security review](security/production-launch-security-review.md) and [production launch approval](product/production-launch-approval.md) for repository evidence versus still-external production controls.

### Production operator

1. Start with the shared [operations index](operations/README.md).
2. Review [runtime configuration](operations/configuration-reference.md), [observability](operations/observability.md), and [background processing](operations/background-processing.md).
3. Use the owning domain's `operations/` area for domain-specific state/diagnosis; for Kingdoms see [Kingdoms operations](domains/kingdoms/operations/README.md).
4. Use the shared [deployment](operations/runbooks/deployment.md), [rollback](operations/runbooks/rollback.md), [backup/restore](operations/runbooks/backup-restore.md), and [incident response](operations/runbooks/incident-response.md) runbooks.
5. Check [production launch approval](product/production-launch-approval.md) before treating the service as production-approved.

A green CI/staging/recovery demonstration or accepted domain increment does not by itself approve production infrastructure or operations.

## Documentation map

| Area | Purpose |
| --- | --- |
| [`adr/`](adr/README.md) | Current architecture view and durable architecture decisions. |
| [`domains/`](domains/README.md) | Current code/domain behavior and ownership plus domain-specific product/security/operations evidence. |
| [`operations/`](operations/README.md) | Shared runtime operations, configuration, deployment, observability, recovery, and runbooks. |
| [`product/`](product/README.md) | Program baseline/status/governance, architecture audits, historical phase-wide evidence, hardening and launch approval. |
| [`security/`](security/README.md) | Shared security baseline, phase-wide threat history, and production-launch security evidence. |

These five directories are the only canonical top-level documentation groups. Do not add parallel `docs/wiki/`, `docs/architecture/`, or top-level `docs/runbooks/` trees.

## Domain documentation rule

Every canonical code domain has exactly one matching documentation root. `docs/domains/README.md` is the only Markdown file directly under `docs/domains/`; all domain material belongs inside one of the domain directories.

For example:

```text
docs/domains/kingdoms/
  README.md
  roster.md
  snapshots.md
  intelligence.md
  csv-migration.md
  transfer-planning.md
  alliance-intelligence.md
  product/
  security/
  operations/
```

The parity, no-flat-file, local-link, naming, and Kingdoms evidence-ownership rules are enforced by `tests/Architecture/RepositoryStructureTest.php` in protected CI.

## Source-of-truth rules

When documents overlap:

1. `product/implementation-plan.md` defines the completed baseline and canonical repository architecture.
2. Accepted ADRs define material architecture decisions.
3. Approved domain-owned increment scopes authorize post-baseline domain capability.
4. Current status records such as `product/current-capability-matrix.md` and `product/production-launch-approval.md` define present capability/go-no-go state.
5. Domain READMEs/capability documents define current business/runtime contracts.
6. Shared/domain security and operations documents define current security/operating requirements.
7. Validation, accessibility, security-review, audit, phase and exit records are evidence for the work they describe.

Code and tests remain authoritative for exact implemented runtime behavior. Documentation drift is a defect to correct, not a compatibility state to preserve.

## Documentation conventions

- Follow the [documentation standard](product/documentation-standard.md).
- Use lowercase kebab-case for descriptive Markdown filenames; `README.md` is the directory-index exception and numbered ADR names remain accepted.
- Use repository-relative Markdown links for navigation.
- Prefer one authoritative contract over duplicated explanations.
- Put domain-specific product/security/operations material under the owning domain.
- Keep only program/shared content in top-level product/security/operations.
- Preserve accepted historical evidence identities and exact evidence where possible when relocating it.
- Record real evidence identifiers; do not mark external production controls complete because CI passed.
- Never commit secrets, credentials, recovery material, private endpoint details, or sensitive production incident payloads.

## Historical delivery and future work

For historical Phase 0–6 program delivery, use the [product index](product/README.md). For accepted post-program domain work, follow the owning domain's product evidence, living contracts, security evidence, operations, and exit records.

Release notes and end-user onboarding remain separate from acceptance evidence.
