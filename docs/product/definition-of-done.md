# Definition of Done

[← Product documentation](README.md)

A change is done only when all applicable conditions are satisfied. The implementation plan remains the authoritative program baseline; this checklist applies to phase work, hardening, maintenance, and explicitly approved post-plan changes.

Normal documentation impact is governed by the [Documentation maintenance standard](documentation-maintenance-standard.md). A change does not require prose churn merely because implementation details moved internally; it does require documentation updates when a documented ownership, behavior, risk, operating model, interface, evidence, architecture, status, or production boundary materially changes.

## Product and scope

- The intended user/operator outcome and acceptance criteria are explicit.
- The work belongs to approved scope; new product capability beyond the implementation plan is approved before implementation.
- Deferred behavior is recorded rather than partially implemented.
- User-facing text/error states are complete.
- Current capability/non-capability navigation is updated when product state changes materially.
- Historical phase/increment/DCP records are not rewritten to imply current status; current-state records are updated separately.

## Architecture and data

- Domain ownership/module boundaries remain within the canonical domain-first structure.
- Every `app/Domain/<Domain>` continues to have matching `docs/domains/<domain>/README.md` documentation.
- A material ownership/public-contract/dependency change updates the code-local README and canonical living contract.
- Authorization and tenant context are explicit for every protected/tenant-scoped operation.
- Database changes have appropriate forward and rollback/recovery strategies.
- Jobs, notifications, integrations, exports, caches, and storage paths have owner, isolation, and idempotency/retry expectations where applicable.
- Material architecture changes have an ADR, including supersession of earlier decisions when required.
- Cross-domain dependency/audit/glossary navigation is updated only when the system-level contract or terminology changes.

## Quality

- Automated tests cover applicable success, failure, authorization, tenant isolation, concurrency, retry, and regression risks.
- Formatting, linting, static analysis, type checks, and production builds pass.
- Dependency, code, and container security checks pass.
- Accessibility/responsive behavior is reviewed for user-facing changes.
- Architecture/repository-structure/documentation tests remain green when files, domain boundaries, public contracts, standards, or documentation structure move.

## Security and privacy

- Sensitive-data classification, authorization, tenancy, secret/token handling, destructive behavior, retention/deletion/anonymization, and trust boundaries remain documented where affected.
- The owning domain security profile/review is updated when the security/privacy contract changes.
- No secrets, credentials, recovery material, or unnecessary private data are committed to documentation/evidence.
- External/infrastructure-dependent controls are not claimed as repository-proven without real external evidence.

## Operations

- Logs use structured fields/correlation identifiers without exposing secrets or unnecessary personal data.
- Metrics, health checks, alerts, and runbooks are updated where behavior/operational risk changes.
- Deployment, migration, rollback, replay/reconciliation, and recovery implications are documented where applicable.
- Secrets/environment requirements are documented without committing secret values.
- Database, private media/object storage, and application-key backup/recovery implications are addressed where applicable.
- Infrastructure-dependent controls are not represented complete without real infrastructure evidence.

## Interfaces and integrations

- Material HTTP/UI/API/CLI/event/job/webhook/import/export/media/external-service changes update the owning interface contract/profile.
- Public/member/manager/admin disclosure and compatibility/version constraints remain explicit where applicable.
- Internal outbox/domain events are not treated as public API/webhook contracts without approved external exposure.
- Integration retries/idempotency/signature/error behavior remains documented and validated when changed.

## Documentation

- Follow the [Repository documentation standard](documentation-standard.md) and [Documentation maintenance standard](documentation-maintenance-standard.md).
- Update the owning document rather than duplicating the same rule in a parallel file.
- New documentation is placed under one of the canonical groups: `adr`, `domains`, `operations`, `product`, or `security`.
- Living domain documentation stays inside the matching `docs/domains/<domain>/` folder; `docs/domains/README.md` remains the only root Markdown file there.
- A material domain change updates applicable domain/security/operations/interfaces/testing documentation according to its impact.
- Repository-relative links resolve and indexes are updated when a primary guide, capability contract, ADR, runbook, review, standard, or status record changes.
- Current-state wording distinguishes Accepted repository/product gates from externally Approved production decisions.
- Evidence records include exact SHAs/run IDs or other immutable references when relevant.
- Historical evidence preserves its recorded acceptance meaning rather than being silently refreshed to current totals.

## Review and acceptance

- Required protected CI/security checks pass on the exact final head.
- Documentation structure/parity/profile/link/standards/index/maintenance checks pass.
- Review comments/threads are resolved.
- No unresolved critical/high security finding remains unless an accountable owner explicitly accepts it under the applicable process.
- Documentation and release/user-impact notes are complete where applicable.
- A change with no documentation edit is defensible because no documented contract materially changed, not because documentation was overlooked.
- The accountable phase/change owner accepts repository/product completion when acceptance is required.
- Production cutover is not Approved until the separate production-approval record and external controls are complete.
