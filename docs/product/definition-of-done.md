# Definition of Done

[← Product documentation](README.md)

A change is done only when all applicable conditions are satisfied. The implementation plan remains the authoritative program baseline; this checklist applies to phase work, hardening, maintenance, and explicitly approved post-plan changes.

## Product and scope

- The intended user or operator outcome and acceptance criteria are explicit.
- The work belongs to approved scope; new product capability beyond the implementation plan is approved before implementation.
- Deferred behavior is recorded rather than partially implemented.
- User-facing text and error states are complete.
- Historical phase records are not rewritten to imply current status; current-state records are updated separately.

## Architecture and data

- Domain ownership and module boundaries are clear and remain within the canonical domain-first structure.
- Authorization and tenant context are explicit for every protected or tenant-scoped operation.
- Database changes have forward and rollback/recovery strategies appropriate to the data risk.
- Jobs, notifications, integrations, exports, caches, and storage paths have an owner, isolation model, and idempotency/retry expectations where applicable.
- Material architecture changes have an ADR, including supersession of earlier decisions when required.

## Quality

- Automated tests cover applicable success, failure, authorization, tenant isolation, concurrency, retry, and regression risks.
- Formatting, linting, static analysis, type checks, and production builds pass.
- Dependency, code, and container security checks pass.
- Accessibility and responsive behavior are reviewed for user-facing changes.
- Architecture/repository-structure tests remain green when files or domain boundaries move.

## Operations

- Logs use structured fields and correlation identifiers without exposing secrets or unnecessary personal data.
- Metrics, health checks, alerts, and runbooks are updated where behavior or operational risk changes.
- Deployment, migration, rollback, and recovery implications are documented.
- Secrets and environment requirements are documented without committing secret values.
- Database, object/private-media, and application-key backup/recovery implications are addressed as applicable.
- Infrastructure-dependent controls are not represented as complete without real infrastructure evidence.

## Documentation

- The owning document is updated rather than duplicating the same rule in a new parallel document.
- New documentation is placed under one of the canonical groups: `adr`, `domains`, `operations`, `product`, or `security`.
- Repository-relative links are valid and indexes are updated when a new primary guide, ADR, runbook, threat model, or status record is added.
- Current-state wording distinguishes Accepted repository/product gates from externally Approved production decisions.
- Evidence records include exact SHAs, run/evidence identifiers, or other immutable references when relevant.

## Review and acceptance

- Required protected CI/security checks pass on the exact final head.
- Review comments and threads are resolved.
- No unresolved critical or high security finding remains unless an accountable owner has explicitly accepted the risk under the applicable process.
- Documentation and release/user-impact notes are complete.
- The accountable phase/change owner accepts repository/product completion when acceptance is required.
- A production cutover is not considered Approved until the separate production-approval record and external controls are complete.
