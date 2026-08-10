# Definition of Done

[← Product documentation](README.md)

A change is done only when all applicable conditions are satisfied. The implementation plan remains the authoritative program baseline; this checklist applies to phase work, hardening, maintenance, and explicitly approved post-plan changes.

## Product and scope

- The intended user/operator outcome and acceptance criteria are explicit.
- The work belongs to approved scope; new product capability beyond the implementation plan is approved before implementation.
- Deferred behavior is recorded rather than partially implemented.
- User-facing text/error states are complete.
- Historical phase records are not rewritten to imply current status; current-state records are updated separately.

## Architecture and data

- Domain ownership/module boundaries remain within the canonical domain-first structure.
- Every `app/Domain/<Domain>` continues to have matching `docs/domains/<domain>/README.md` documentation.
- Authorization and tenant context are explicit for every protected/tenant-scoped operation.
- Database changes have appropriate forward and rollback/recovery strategies.
- Jobs, notifications, integrations, exports, caches, and storage paths have owner, isolation, and idempotency/retry expectations where applicable.
- Material architecture changes have an ADR, including supersession of earlier decisions when required.

## Quality

- Automated tests cover applicable success, failure, authorization, tenant isolation, concurrency, retry, and regression risks.
- Formatting, linting, static analysis, type checks, and production builds pass.
- Dependency, code, and container security checks pass.
- Accessibility/responsive behavior is reviewed for user-facing changes.
- Architecture/repository-structure tests remain green when files, domain boundaries, or documentation structure move.

## Operations

- Logs use structured fields/correlation identifiers without exposing secrets or unnecessary personal data.
- Metrics, health checks, alerts, and runbooks are updated where behavior/operational risk changes.
- Deployment, migration, rollback, and recovery implications are documented.
- Secrets/environment requirements are documented without committing secret values.
- Database, private media/object storage, and application-key backup/recovery implications are addressed where applicable.
- Infrastructure-dependent controls are not represented complete without real infrastructure evidence.

## Documentation

- Follow the [repository documentation standard](documentation-standard.md).
- Update the owning document rather than duplicating the same rule in a parallel file.
- New documentation is placed under one of the canonical groups: `adr`, `domains`, `operations`, `product`, or `security`.
- Living domain documentation stays inside the matching `docs/domains/<domain>/` folder; `docs/domains/README.md` remains the only root Markdown file there.
- A material domain change updates both the canonical docs-domain contract and the code-local `app/Domain/<Domain>/README.md` when its ownership/public-contract/dependency navigation changes.
- Repository-relative links resolve and indexes are updated when a primary guide, capability contract, ADR, runbook, threat model, or status record changes.
- Current-state wording distinguishes Accepted repository/product gates from externally Approved production decisions.
- Evidence records include exact SHAs/run IDs or other immutable references when relevant.

## Review and acceptance

- Required protected CI/security checks pass on the exact final head.
- Documentation structure/parity/no-flat-file/link checks pass.
- Review comments/threads are resolved.
- No unresolved critical/high security finding remains unless an accountable owner explicitly accepts it under the applicable process.
- Documentation and release/user-impact notes are complete.
- The accountable phase/change owner accepts repository/product completion when acceptance is required.
- Production cutover is not Approved until the separate production-approval record and external controls are complete.
