# Membership invitations

[← Memberships domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Memberships

## 1. Purpose

Defines the controlled Alliance invitation lifecycle used for direct member invitations and Recruitment accepted-candidate handoff.

Invitations are expiring email-bound bearer-access records. They are distinct from active membership state and from Authentication-owned identity assurance.

## 2. Scope and non-scope

In scope:

- invitation issue, expiry, revoke, resend/rotation, and acceptance;
- same-Alliance/email serialization;
- normalized email binding;
- member-capacity checks supplied by Platform;
- accepted-candidate handoff from Recruitment; and
- membership activation/reactivation plus Member-role restoration where required.

Out of scope:

- global account authentication;
- role/permission vocabulary ownership;
- Recruitment candidate persistence; and
- recoverable/public invitation-link storage.

## 3. Model and state

An invitation belongs to one Alliance and normalized email, carries creator/expiry/status metadata, and stores only protected verification state rather than recoverable plaintext bearer material.

The meaningful lifecycle is:

- pending and unexpired;
- accepted;
- revoked; or
- expired by time evaluation.

Resend rotates the bearer material and refreshes expiry for an eligible pending invitation.

## 4. Invariants

1. Invitation state is Alliance scoped and email bound.
2. Only protected verification state is persisted; plaintext bearer material is not recoverable from normal storage.
3. An already-active member cannot be invited again.
4. Issuing a new pending invitation for the same Alliance/email revokes earlier pending invitations under serialization.
5. Accepted or revoked invitations cannot be resent or accepted again.
6. Acceptance requires an authenticated verified User whose normalized email matches the invitation email.
7. Acceptance is transactional with membership activation/reactivation and required role restoration.
8. Capacity/lifecycle checks supplied by Platform cannot be bypassed by direct invitation creation.
9. Recruitment consumes the supported invitation contract and does not own invitation persistence.

## 5. Workflows

### Issue

A manager with `invitations.manage` submits an email. Memberships normalizes the email, rejects active-member duplication, checks capacity/lifecycle constraints, serializes same-Alliance/email pending state, revokes superseded pending invitations, and issues new bearer material through the controlled response/delivery flow.

### Resend

An eligible pending invitation receives rotated bearer material and refreshed expiry. Existing plaintext is not recovered.

### Revoke

An authorized manager revokes a pending invitation. It becomes unusable for acceptance/resend.

### Accept

The authenticated User presents the bearer invitation, must have matching normalized email, and must satisfy verification requirements. Memberships transactionally creates/reactivates membership, restores the built-in Member role when appropriate, marks the invitation accepted, and records required evidence.

## 6. Authorization, tenancy and privacy

Issue/revoke/resend requires active Alliance context plus `invitations.manage`; privileged mutations require recent password confirmation where required.

Acceptance is a bearer-token plus authenticated/verified-email flow. The bearer link is secret and must not be exposed in routine logs, docs, public pages, or analytics.

## 7. Persistence and query semantics

Memberships owns invitation records and verification state. Identity owns User/account data; Authorization owns role assignments; Recruitment owns candidate state.

Invitation lookup/acceptance must validate state, expiry, Alliance relationship, and email binding before membership changes.

## 8. Events, integrations and background processing

Issue/revoke/resend/acceptance may create audit/outbox evidence. Internal invitation events do not automatically create public webhooks.

Expiry is enforced from persisted timestamps; no background worker grants membership because an invitation exists.

## 9. Failure, idempotency and concurrency

- Same-Alliance/email issuance is serialized to prevent multiple current pending bearer links.
- Invalid, expired, revoked, or consumed invitations fail closed.
- Email mismatch fails closed.
- Repeated acceptance cannot create duplicate active memberships.
- Transaction failure must not leave accepted invitation state without the corresponding membership transition.

## 10. Operations and observability

Diagnose invitation failures using status, expiry, normalized email match, active-member state, capacity entitlement, and target Alliance lifecycle.

Never log or display bearer material outside the controlled issue/acceptance flow.

## 11. Tests and validation

Tests should cover:

- issue/expiry/revoke/resend/acceptance;
- protected verifier persistence;
- same-email pending serialization;
- active-member duplicate rejection;
- email normalization/mismatch;
- capacity/lifecycle checks;
- membership activation/reactivation; and
- Recruitment handoff boundary.

## 12. Related documentation

- [Memberships domain](README.md)
- [Identity](../identity/README.md)
- [Authorization](../authorization/README.md)
- [Recruitment](../recruitment/README.md)
- [Platform](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
