# Memberships interfaces

[← Memberships domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Memberships  
**Code owner:** `app/Domain/Memberships`  
**Primary boundary:** Alliance membership administration and controlled bearer invitation issue/show/acceptance  
**P4 inventory decision:** Focused contract reused — `../invitations.md`

## 1. Boundary purpose and ownership

Memberships owns the User↔Alliance membership lifecycle and the controlled invitation boundary used by direct Alliance administration and Recruitment onboarding. It also adapts first-party membership-role route requests into Authorization-owned role assignment/removal semantics.

Identity authenticates the User, Alliances establishes tenant context, and Authorization owns role/permission vocabulary; Memberships owns whether/how a User is related to the Alliance.

## 2. Surface inventory

Material first-party routes in `routes/web.php` include:

- `GET /invitations/{token}` — invitation landing/show boundary;
- authenticated verified `POST /invitations/{token}/accept`;
- manager invitation create/resend/revoke;
- manager membership status mutation;
- manager membership-role assign/remove adapters; and
- authenticated self-leave.

Manager mutations are inside the recent password-confirmation boundary. Invitation token paths require exactly 64 hexadecimal characters at the route level.

## 3. Callers, authorization and tenancy

Invitation landing is reachable by possession of the secret token but does not itself grant Alliance membership. Acceptance requires authenticated/verified Identity and the invitation's bound email/tenant/lifecycle rules documented in [Membership invitations](../invitations.md).

Invitation administration requires `invitations.manage`; membership administration requires `membership.manage`; role actions additionally consume Authorization hierarchy/safety rules. All tenant-owned records are re-resolved under the active Alliance.

## 4. Input and validation contracts

Invitation issue/resend/revoke/acceptance inputs, expiry, token hashing, email binding, reuse/concurrency and Recruitment handoff follow [Membership invitations](../invitations.md).

Membership status mutation only accepts supported lifecycle transitions through owning actions. Role route identifiers are adapters into Authorization-owned role contracts and do not permit arbitrary role creation.

## 5. Output and disclosure contracts

Invitation landing/acceptance exposes only the minimum invitation/Alliance information required for the invited user workflow. Raw token material is never routine persisted/logged/audit payload.

Member/manager workspaces expose tenant-safe membership state according to permission. Invitation and membership data from another Alliance is never disclosed because a caller knows an identifier.

## 6. Internal actions, queries and services

Supported Memberships contracts include invitation create/resend/revoke/accept actions, membership status transitions, leave behavior, active membership lookup/context support, and the Recruitment→Membership invitation/onboarding handoff.

Role assignment/removal calls Authorization's supported actions; Memberships does not redefine permission semantics. Kingdoms may reference same-Alliance memberships without converting membership identity into game identity.

## 7. Events, outbox and cross-domain consumers

Material invitation/membership transitions may record audit/outbox evidence. Recruitment consumes Memberships onboarding/invitation semantics for accepted candidates; Alliances/Authorization consume active membership for tenant/permission evaluation.

Memberships events do not automatically define external membership webhooks. Integrations external eligibility remains independent.

## 8. Commands, jobs and scheduled work

Memberships has no domain-specific custom command or scheduler. Invitation expiry is evaluated from persisted expiry state at use/management time; no background job silently activates membership.

Recruitment/Platform scheduled workflows may eventually affect related candidate/account lifecycle but do not own Memberships activation semantics.

## 9. Files, imports, exports and external dependencies

Memberships has no current file import/export contract. Invitation workflows may depend on configured mail delivery for notifying recipients, but the durable invitation record/token lifecycle is the application contract; external mail success is not membership activation.

Operational behavior is documented in [Memberships operations](../operations/README.md).

## 10. Failure, idempotency, versioning and compatibility

Invalid/expired/revoked/used/wrong-email invitation access fails closed according to [Membership invitations](../invitations.md). Acceptance is serialized/idempotent at the owning contract so retries cannot create uncontrolled duplicate membership state.

Hierarchy, active-R5 leadership, cross-tenant, or invalid lifecycle mutations fail closed. The 64-hex route-token shape and email-bound acceptance semantics are compatibility/security-relevant first-party contracts.

## 11. Explicit non-capabilities

Memberships does not:

- authenticate users;
- treat invitation possession alone as membership authorization;
- expose invitations as non-secret public links;
- define role/permission vocabulary;
- own Recruitment candidate persistence;
- own Kingdoms game identity; or
- provide a public membership-management API.

## 12. Focused contracts, evidence and related documentation

P4 reuses [Membership invitations](../invitations.md) as the complete bearer-token invitation contract.

Related documentation:

- [Memberships domain](../README.md)
- [Membership invitations](../invitations.md)
- [Memberships security](../security/README.md)
- [Memberships operations](../operations/README.md)
- [Authorization interfaces](../../authorization/interfaces/README.md)
- [Recruitment application intake](../../recruitment/application-intake.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
