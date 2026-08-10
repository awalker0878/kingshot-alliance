# Memberships domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Memberships`  
**Primary authorization boundary:** active Alliance context; `membership.manage` and `invitations.manage` for privileged administration

## 1. Purpose and ownership

Memberships owns the relationship between a global User and an Alliance, including membership lifecycle, invitation lifecycle, leave/removal behavior, administration safety rules, and the supported invitation contract consumed by Recruitment.

Memberships does not own global User identity, the Alliance aggregate, or role/permission definitions.

## 2. Scope

### In scope

- Alliance membership persistence and lifecycle states;
- invitation creation, expiry, resend, revocation, and acceptance;
- membership activation/reactivation;
- voluntary leave and administrative status changes;
- membership-administration hierarchy safety;
- last-active-Owner safety in coordination with Authorization role assignment; and
- supported membership/invitation handoff used by Recruitment.

### Out of scope

- authentication/MFA, owned by Identity;
- active Alliance context, owned by Alliances;
- role/permission vocabulary and role assignment, owned by Authorization;
- Recruitment candidate persistence; and
- game-side roster identity, owned by Kingdoms.

## 3. Domain model

### Membership states

The membership status vocabulary is:

| Status | Meaning |
| --- | --- |
| `invited` | Defined in lifecycle vocabulary; normal invitation acceptance activates the membership rather than leaving it in this state. |
| `active` | The User may establish active-Alliance context and receive permissions from assigned roles. |
| `suspended` | Relationship retained but cannot establish active-Alliance access. |
| `left` | Set by the member's explicit leave action; assigned roles are removed. |
| `removed` | Set by an authorized administrator; assigned roles are removed. |

Administrative status mutation supports `active`, `suspended`, and `removed`. A User changes their own active membership to `left` through the dedicated leave workflow.

### Invitations

Alliance invitations are separate bearer-token records. The default lifetime is **72 hours** unless `identity.invitation_ttl_hours` is configured differently.

Only the token hash is stored.

## 4. Core invariants

1. A membership always belongs to exactly one User and one Alliance.
2. Only `active` membership may establish normal active-Alliance access.
3. Administrative self-removal does not use the general membership-status mutation; the User uses the dedicated leave workflow.
4. Removal and voluntary leave strip role assignments so dormant privileges do not silently reappear.
5. Reactivating a membership with no role assignment restores the built-in Member role through the supported Authorization contract.
6. An Alliance must retain at least one active Owner.
7. A pending invitation is tenant-bound, email-bound, expiring bearer access; only its hash is stored.
8. An already-active member cannot be invited again.
9. New pending invitation issuance for the same Alliance/email revokes earlier pending invitations for that email under serialization.
10. Accepted or revoked invitations cannot be resent.
11. Invitation acceptance requires the authenticated User's normalized email to match the invitation email.
12. Invitation acceptance and membership changes are transactional.

## 5. Lifecycles and workflows

### Membership administration

Membership administration requires an active membership carrying `membership.manage`.

The effective role-rank safety model used for management is:

| Effective role rank | Rank |
| --- | ---: |
| Owner | 100 |
| Leader | 80 |
| Officer | 60 |
| Recruiter / Event Coordinator / Content Manager | 40 |
| Member | 10 |

A non-Owner administrator may manage only a membership below their own effective rank. Administrators cannot use the general status action on their own membership.

Suspending/removing/voluntarily leaving as the last active Owner is rejected until another active Owner exists.

### Create invitation

Creating an invitation requires `invitations.manage`. The email is normalized, active-member duplicates are rejected, and Platform/member-capacity entitlement checks are enforced.

A new pending invitation for the same Alliance/email is serialized against the Alliance and revokes earlier pending invitations for that email.

### Resend invitation

Resending an eligible pending invitation rotates the bearer token and refreshes expiry. Accepted/revoked invitations cannot be resent.

### Revoke invitation

An authorized manager may revoke a pending invitation. Revoked invitations cannot be accepted or resent.

### Accept invitation

Acceptance requires:

- pending/unexpired token;
- authenticated User normalized email matching invitation email; and
- transactional invitation/membership change.

Acceptance creates or reactivates the membership, assigns the built-in Member role if required, marks the invitation accepted, records audit evidence, and emits durable outbox state.

Invitation links are secrets. Revoke or resend instead of trying to recover an old plaintext token.

### Leave Alliance

A User leaves through the dedicated self-service action. The active membership becomes `left` and role assignments are removed, subject to last-active-Owner safety.

## 6. Authorization and tenancy

Membership reads/mutations are resolved under explicit active Alliance context when performed from Alliance management.

- `membership.manage` controls administrative membership status changes.
- `invitations.manage` controls invitation create/revoke/resend.
- invitation acceptance also requires verified authenticated Identity and normalized email match.

Role rank/Owner safety is enforced in addition to permission checks; `membership.manage` is not permission to bypass last-Owner or hierarchy rules.

## 7. Cross-domain contracts

### Consumes

- **Identity** — global User identity, verified email, normalized email, recent password confirmation.
- **Alliances** — active Alliance context.
- **Authorization** — effective role rank, Member/Owner role assignment/removal, permission evaluation.
- **Platform** — member-capacity entitlement and Alliance lifecycle state.
- **Audit** — attributable invitation/membership evidence.

### Exposes

- active membership used by Alliances/Authorization to establish tenant access;
- supported invitation creation/acceptance contract consumed by Recruitment; and
- membership identity optionally referenced by Kingdoms roster links without transferring membership ownership.

## 8. Persistence and data ownership

Memberships owns Alliance-membership and invitation records. Invitation plaintext tokens are never persisted; only hashes are retained.

Role assignment persistence remains Authorization-owned. User account data remains Identity-owned. Recruitment candidates remain Recruitment-owned.

## 9. Events, outbox and integrations

Invitation creation/revocation/resend/acceptance, membership status changes, and leave transitions create audit/outbox evidence as required.

Internal membership outbox events do not automatically become public webhook contracts.

## 10. HTTP, UI and API surfaces

First-party Alliance membership/invitation administration is protected by active Alliance context, the owning permission, and recent password confirmation for privileged mutations.

Invitation acceptance is a bearer-token plus authenticated-email workflow; the link should be handled as a secret.

## 11. Background processing

Normal membership/invitation state changes are request-driven. Expiry is enforced from persisted expiry timestamps; no hidden background process grants membership by inference.

## 12. Failure, idempotency and concurrency

- Invitation issuance serializes same-Alliance/same-email pending invitations.
- Acceptance is transactional and fails for invalid/expired/revoked/already-consumed token state.
- Email mismatch fails closed.
- Last-active-Owner removal/leave/suspension fails closed.
- Repeated role-independent membership reactivation restores Member only when no role exists.
- Cross-Alliance membership/invitation IDs are re-resolved and rejected.

## 13. Security and privacy

Invitation links are bearer secrets. Never log, document, or persist plaintext invitation tokens beyond the controlled issue/acceptance boundary.

Membership email/identity data is tenant-private and must not leak through game roster/public content merely because a roster/profile can reference a membership.

## 14. Observability and operations

Operators should distinguish invitation token state, email mismatch, capacity entitlement, membership state, role hierarchy, and last-Owner protection when diagnosing failures.

See [Identity](../identity/README.md), [Authorization](../authorization/README.md), [Platform](../platform/README.md), and [Security baseline](../../security/security-baseline.md).

## 15. Testing and architecture enforcement

Tests should protect:

- invitation expiry/revocation/resend/acceptance;
- email normalization/match;
- same-email pending-invitation serialization;
- membership lifecycle transitions;
- role stripping/restoration behavior;
- management hierarchy;
- last-active-Owner safety;
- cross-Alliance isolation; and
- the architecture boundary that Recruitment consumes a Memberships contract rather than invitation persistence internals.

## 16. Explicit non-capabilities

Memberships does not:

- authenticate Users;
- define Alliance role/permission vocabulary;
- own Recruitment candidate records;
- own Kingdoms roster identity; or
- treat an invitation as a public non-secret URL.

## 17. Capability documents

No separate Memberships capability files are required at present.

## 18. Related documentation

- [Identity domain](../identity/README.md)
- [Alliances domain](../alliances/README.md)
- [Authorization domain](../authorization/README.md)
- [Recruitment domain](../recruitment/README.md)
- [Kingdoms domain](../kingdoms/README.md)
- [Platform domain](../platform/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Memberships/README.md`](../../../app/Domain/Memberships/README.md)
