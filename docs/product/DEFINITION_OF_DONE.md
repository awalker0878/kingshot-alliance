# Definition of Done

A change is done only when all applicable conditions are satisfied.

## Product and scope

- The outcome and acceptance criteria are explicit.
- The work belongs to the active phase.
- Deferred behavior is recorded rather than partially implemented.
- User-facing text and error states are complete.

## Architecture and data

- Domain ownership and module boundaries are clear.
- Authorization and, after Phase 1, tenant context are explicit.
- Database changes have forward and rollback strategies.
- Jobs, notifications, exports, caches, and storage paths have an owner and isolation model.
- Material architecture changes have an ADR.

## Quality

- Automated tests cover success, failure, authorization, isolation, and concurrency risks.
- Formatting, linting, static analysis, type checks, and production builds pass.
- Dependency and container security checks pass.
- Accessibility and responsive behavior are reviewed.

## Operations

- Logs use structured fields and correlation identifiers.
- Metrics, health checks, alerts, and runbooks are updated.
- Deployment and rollback steps are documented.
- Secrets and environment requirements are documented without committing values.
- Data backup and restore implications are addressed.

## Review and acceptance

- CI passes.
- Review comments are resolved.
- No unresolved critical or high security finding remains.
- Documentation and release notes are complete.
- The phase owner accepts the result.
