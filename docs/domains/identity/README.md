# Identity domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Identity`  
**Primary authorization boundary:** authenticated global User identity with verified-email/password/MFA assurance where required

## 1. Purpose and ownership

Identity owns the global user account and account-security lifecycle. One User identity exists regardless of how many Alliances the User joins.

Identity owns authentication, email verification, password/session lifecycle, profile/account security, TOTP MFA, and one-time recovery codes. It does not own Alliance tenancy, membership state, Alliance roles/permissions, or game-side identity.

## 2. Scope

### In scope

- global User identity;
- registration/login/logout/account recovery;
- verified-email state;
- password/session lifecycle;
- profile/account-security state;
- TOTP MFA enrollment/use; and
- one-time MFA recovery codes.

### Out of scope

- active Alliance selection/context, owned by Alliances;
- Alliance membership/invitations, owned by Memberships;
- Alliance roles/permissions, owned by Authorization;
- platform-administrator grants, owned by Platform; and
- neutral Kingshot player/alliance identity, owned by Kingdoms.

## 3. Domain model

### User

`User` is global application identity. The account is not duplicated per Alliance. A single User can participate in multiple Alliances through Memberships-owned relationships.

### Email verification

Verified email is an identity-assurance prerequisite for Alliance creation/activation, invitation acceptance, and Alliance-scoped authenticated workflows.

### Password/session assurance

Authenticated sessions establish User identity. Security-sensitive HTTP mutations require recent password confirmation in addition to the operation's domain authorization.

### MFA and recovery codes

TOTP MFA and one-time recovery codes belong to the global Identity account. MFA administration itself requires verified identity and recent password confirmation.

## 4. Core invariants

1. User identity is global and is never duplicated per Alliance tenant.
2. Identity alone never grants Alliance access.
3. Verified email is required before the accepted privileged/Alliance workflows that depend on verified identity.
4. Recent password confirmation strengthens sensitive operations but never replaces domain authorization.
5. MFA strengthens identity assurance but never replaces Alliance membership/permission checks.
6. TOTP recovery codes are Identity-owned secrets and must not be treated as tenant/business data.
7. Platform-administrator authority remains a separate Platform grant even though it uses Identity assurance.

## 5. Lifecycles and workflows

### Authenticate and maintain account

Users register/sign in, verify email, manage password/session state, and maintain profile/account-security settings through Identity-owned workflows.

### Verify email

Email verification gates Alliance creation, active-Alliance activation, invitation acceptance, and authenticated Alliance-scoped workflows where verified identity is required.

### Confirm password for privileged changes

Security-sensitive routes across Memberships, Authorization, Content, Events, Recruitment, Contributions, Integrations, Kingdoms, and Platform use recent password confirmation at the HTTP boundary.

Identity provides the assurance mechanism; the owning domain still decides whether the User may perform the action.

### Manage MFA

TOTP MFA and recovery-code management requires verified identity and recent password confirmation. Platform administrators additionally require MFA for cross-tenant access.

## 6. Authorization and tenancy

Identity authenticates the User; it does not establish Alliance authorization.

Alliance-scoped access additionally requires:

- explicit active Alliance context from Alliances;
- active Memberships-owned membership; and
- the permission/policy required by the owning feature domain.

A global User ID is never a substitute for `alliance_id` scoping.

## 7. Cross-domain contracts

### Consumes

- **Platform** — account-deletion/legal-hold eligibility may affect whether a global account can be destructively processed.
- shared application/session infrastructure used to persist authenticated identity state.

### Exposes

- authenticated User identity;
- verified-email state;
- recent password-confirmation assurance;
- MFA/recovery-code assurance; and
- global profile identity used by Memberships/other domains without transferring Identity ownership.

## 8. Persistence and data ownership

Identity owns User account/authentication/profile/security state and MFA/recovery-code material. Alliance-owned membership/role/business records remain in their owning domains.

Deletion/anonymization may be orchestrated by Platform, but that process must respect Identity-owned account state and preserve only the pseudonymized historical evidence permitted by the wider lifecycle contract.

## 9. Events, outbox and integrations

Security-relevant account changes are auditable where required. Identity does not treat an internal audit/outbox record as a public integration contract.

No external OAuth or public identity API is implied by current Integrations behavior.

## 10. HTTP, UI and API surfaces

Identity owns first-party account/authentication flows such as registration, sign-in/out, verification, password reset/confirmation, profile security, and MFA management.

Alliance feature routes use Identity assurance but remain owned by their respective domains.

## 11. Background processing

Identity does not define a hidden background worker for Alliance authorization. Session/verification/account-security behavior is request-driven except for shared framework/platform maintenance where documented elsewhere.

## 12. Failure, idempotency and concurrency

- Unverified identity fails closed for workflows requiring verification.
- Stale/missing recent password confirmation redirects/refuses the sensitive mutation until reconfirmed.
- MFA failure never falls back to Alliance roles as an identity substitute.
- Account-security recovery should use supported Identity flows rather than direct persistence edits.

## 13. Security and privacy

Passwords, MFA secrets, recovery codes, session secrets, and other authentication material must never be logged or exposed to other tenants.

Identity assurance must be combined with the owning domain's authorization; strong authentication by itself is not permission.

## 14. Observability and operations

Operators should diagnose authentication, verification, password-confirmation, session, and MFA failures separately from Alliance-context/permission failures.

See [Security baseline](../../security/security-baseline.md), [Observability](../../operations/observability.md), and [Platform domain](../platform/README.md).

## 15. Testing and architecture enforcement

Tests should protect:

- registration/login/logout/account recovery;
- email-verification gates;
- password-confirmation gates;
- MFA/recovery-code behavior;
- session security; and
- the architectural separation between global Identity and Alliance tenancy/authorization.

## 16. Explicit non-capabilities

Identity does not:

- own Alliance membership/invitations;
- assign Alliance roles or permissions;
- establish active Alliance context;
- grant platform-administrator authority; or
- own neutral game-player/game-alliance identity.

## 17. Capability documents

No separate Identity capability files are required at present.

## 18. Related documentation

- [Alliances domain](../alliances/README.md)
- [Memberships domain](../memberships/README.md)
- [Authorization domain](../authorization/README.md)
- [Platform domain](../platform/README.md)
- [Kingdoms domain](../kingdoms/README.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Identity/README.md`](../../../app/Domain/Identity/README.md)
