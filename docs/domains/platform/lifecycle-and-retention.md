# Alliance lifecycle and retention

[← Platform domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Platform

## 1. Purpose

Defines cross-tenant Alliance lifecycle control, legal-hold boundaries, restoration/deletion timing, account-deletion orchestration, retention cleanup, and tenant-complete administrative export.

This capability is separated because destructive lifecycle/data-governance operations have a materially stronger authorization and failure boundary than ordinary plan/settings administration.

## 2. Scope and non-scope

In scope:

- Alliance lifecycle transitions;
- close/delete/restore requirements and retention deadline;
- legal holds;
- User account-deletion eligibility/cooling-off orchestration;
- operational retention cleanup;
- Alliance ownership transfer as a lifecycle operation; and
- tenant-complete administrative export evidence/bounds.

Out of scope:

- feature-domain semantic ownership of exported rows;
- support impersonation;
- payment processing;
- Alliance RBAC; and
- production approval based solely on repository validation.

## 3. Model and state

Alliance lifecycle states are:

- active;
- suspended;
- closed; and
- deleted.

Tenant context/API authentication accepts only active Alliances.

Closing establishes the documented restoration/retention deadline. Logical deletion requires prior closed state and absence of a blocking active legal hold. Restoration is permitted only within the supported retention window.

Legal holds may target a User or Alliance and block destructive processing for that subject.

## 4. Invariants

1. Platform lifecycle authority is separate from Alliance roles.
2. Cross-tenant web operations require verified/MFA-backed Platform administrator access and recent password confirmation.
3. Lifecycle mutations are reasoned, attributable, transactional, and row-locked where required.
4. Non-active Alliances cannot establish normal tenant/API access.
5. Logical deletion requires closed state first.
6. Active legal hold blocks the applicable destructive processing.
7. Restoration after the retention deadline fails closed.
8. Ownership transfer target must be an active membership in the same Alliance.
9. Account deletion cannot bypass Platform-admin status, active ownership, or legal hold restrictions.
10. Export/redaction orchestration does not transfer business-data ownership into Platform.

## 5. Workflows

### Suspend/close/delete/restore Alliance

An authorized Platform administrator performs the requested transition with reason, transaction/locking, audit, and durable event evidence. Close records the restoration/retention deadline. Delete requires closed state and hold checks. Restore requires an unexpired restoration window.

### Transfer ownership

The target is validated as an active same-Alliance membership. The target receives Owner and previous owners are demoted according to the supported workflow; Memberships/Authorization retain ownership of their persistence.

### Account deletion

After the documented cooling-off period and eligibility checks, the workflow revokes account tokens, ends active memberships, anonymizes the User, and preserves only pseudonymized evidence permitted by policy/contract.

### Operational retention

Scheduled maintenance redacts/removes eligible old integration/usage/export metadata according to the documented retention contract while respecting legal holds and semantic ownership.

### Tenant-complete export

Platform discovers tenant-owned rows by `alliance_id`, exports the requested Alliance's rows, redacts known secret/verifier columns, enforces the synchronous safety bound, and records schema/version/requester/checksum/row-count evidence.

## 6. Authorization, tenancy and privacy

This is a cross-tenant Platform-admin capability. It never obtains authority by switching to the target Alliance's `AllianceContext`.

Sensitive/destructive actions require the Platform administrator grant plus Identity assurance. Legal holds, redaction, anonymization, and export behavior are privacy/data-governance controls and must fail closed.

## 7. Persistence and query semantics

Platform owns lifecycle orchestration state, legal holds, export evidence, usage/retention metadata, and other Platform control records.

Feature domains continue to own the semantic meaning of tenant rows discovered/exported or affected by lifecycle orchestration.

## 8. Events, integrations and background processing

Lifecycle/account-retention transitions may use the shared transactional outbox and scheduled maintenance workers.

Integrations credentials/webhook persistence remains Integrations-owned even when Platform retention deletes/redacts eligible records through an approved lifecycle contract.

## 9. Failure, idempotency and concurrency

- Lifecycle rows are locked during sensitive transitions.
- Delete fails unless closed and hold-free.
- Restore fails after the retention deadline.
- Ownership transfer fails for non-active/cross-Alliance targets.
- Destructive account processing fails under legal hold/ownership/admin restrictions.
- Export aborts/fails safely at the implemented synchronous size bound instead of monopolizing the request worker.

## 10. Operations and observability

Operators should retain reason, actor, target, transition state, hold evaluation, retention deadline, export checksum/count evidence, and correlated audit/outbox state without retaining secret values.

Use shared deployment/recovery/observability runbooks for infrastructure failures.

## 11. Tests and validation

Tests should cover:

- lifecycle transition rules;
- active-tenant/API gating;
- legal-hold blocking;
- delete/restore deadline behavior;
- ownership transfer scope;
- account-deletion eligibility/anonymization;
- retention behavior; and
- export tenant scope/redaction/bounds/evidence.

## 12. Related documentation

- [Platform domain](README.md)
- [Transactional outbox](transactional-outbox.md)
- [Identity](../identity/README.md)
- [Memberships](../memberships/README.md)
- [Authorization](../authorization/README.md)
- [Security baseline](../../security/security-baseline.md)
- [Operations](../../operations/README.md)
