# Memberships domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Memberships`  
**Primary authorization boundary:** active Alliance context; `membership.manage` and `invitations.manage` for privileged administration

## 1. Purpose and ownership

Memberships owns the User↔Alliance membership relationship, membership lifecycle, voluntary leave/admin state changes, hierarchy/last-Owner safety, and the controlled invitation contract used by direct administration and Recruitment onboarding.

Identity owns the User, Alliances owns tenant context, and Authorization owns role/permission definitions and assignments.

## 2. Scope

In scope: membership states, activation/reactivation, suspend/remove/leave behavior, administration hierarchy/Owner safety, and Alliance invitations.

Out of scope: authentication/MFA, Alliance aggregate/context, role vocabulary/permission evaluation, Recruitment candidate persistence, and Kingdoms game identity.

## 3. Domain model

Membership status vocabulary includes `invited`, `active`, `suspended`, `left`, and `removed`; normal accepted invitation flow activates membership rather than leaving it in an intermediate invited state.

The bearer-token invitation lifecycle is independently documented in [Membership invitations](invitations.md).

## 4. Core invariants

1. A membership belongs to exactly one User and one Alliance.
2. Only active membership may establish normal tenant access.
3. Self-leave uses the dedicated workflow rather than general admin status mutation.
4. Leave/removal strips role assignments; reactivation with no role restores built-in Member through Authorization.
5. An Alliance retains at least one active Owner.
6. Administration respects effective role hierarchy and tenant scope.
7. Invitation rules follow [invitations.md](invitations.md).

## 5. Lifecycles and workflows

Authorized membership administration may activate, suspend, or remove other eligible memberships according to hierarchy and last-Owner rules. Users leave through the dedicated self-service transition to `left`.

Invitation issue/resend/revoke/acceptance and Recruitment handoff are defined in [Membership invitations](invitations.md).

## 6. Authorization and tenancy

Membership administration is active-Alliance scoped and requires `membership.manage`; invitation administration requires `invitations.manage`. Role hierarchy and last-active-Owner protection apply in addition to permission checks. Privileged HTTP mutations use required Identity assurance.

## 7. Cross-domain contracts

Consumes Identity, Alliances, Authorization rank/roles, Platform lifecycle/capacity, and Audit/outbox evidence.

Exposes active membership used by tenant context/permission evaluation, the controlled [invitation contract](invitations.md) consumed by Recruitment, and optional membership references consumed by Kingdoms without ownership transfer.

## 8. Persistence and data ownership

Memberships owns membership and invitation records. Authorization owns role assignments; Identity owns account data; Recruitment owns candidates; Kingdoms owns game roster identity.

## 9. Events, outbox and integrations

Membership/invitation transitions create audit/outbox evidence where required. Internal events are not automatically public webhook contracts.

## 10. HTTP, UI and API surfaces

First-party Alliance membership/invitation administration is permission/tenant protected. Invitation acceptance is the controlled bearer + authenticated-email workflow documented in [invitations.md](invitations.md).

## 11. Background processing

Membership transitions are request driven. Invitation expiry is evaluated from persisted expiry state; no background process grants membership by inference.

## 12. Failure, idempotency and concurrency

Last-Owner/hierarchy/cross-tenant violations fail closed. Role restoration on reactivation uses the supported Authorization contract. Invitation-specific serialization/idempotency is defined in [invitations.md](invitations.md).

## 13. Security and privacy

Membership identity/email data is tenant private. Invitation access material is secret and governed by [Membership invitations](invitations.md).

## 14. Observability and operations

Diagnose membership status, role hierarchy, last-Owner constraints, Platform capacity/lifecycle, and invitation state separately.

## 15. Testing and architecture enforcement

Tests protect membership transitions, role strip/restore behavior, hierarchy/last-Owner safety, tenant isolation, invitation lifecycle, and Recruitment boundary.

## 16. Explicit non-capabilities

Memberships does not authenticate Users, define permission vocabulary, own Recruitment candidates/Kingdoms identity, or treat invitations as public non-secret links.

## 17. Capability documents

- [Membership invitations](invitations.md) — issue/expiry/revoke/resend/acceptance, email binding, concurrency, and Recruitment handoff.

## 18. Related documentation

- [Identity](../identity/README.md)
- [Alliances](../alliances/README.md)
- [Authorization](../authorization/README.md)
- [Recruitment](../recruitment/README.md)
- [Kingdoms](../kingdoms/README.md)
- [Platform](../platform/README.md)
- [`app/Domain/Memberships/README.md`](../../../app/Domain/Memberships/README.md)
