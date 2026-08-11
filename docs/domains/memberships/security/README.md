# Memberships security profile

[← Memberships domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Memberships  
**Code owner:** `app/Domain/Memberships`  
**Primary security boundary:** active User↔Alliance membership plus hierarchy/last-Owner safety, with bearer invitation access handled as a separate secret lifecycle

## 1. Security purpose and scope

Memberships protects the relationship that permits a global User to participate in an Alliance. It prevents stale/inactive membership access, unsafe hierarchy changes, Owner lockout/takeover, and unauthorized membership creation through invitations.

The independent bearer-token lifecycle is reviewed in [Invitations security review](invitations-security-review.md).

## 2. Assets and sensitive data

Assets include membership status, Alliance/User association, membership history, role-stripping/reactivation implications, invitation recipient email, invitation status/expiry, and protected invitation token verifier state.

Membership identity/email is tenant-private. Invitation bearer material is a security secret and must not be retained plaintext in routine persistence/logging/audit/outbox state.

## 3. Actors, authentication and authorization

Normal tenant access requires an authenticated User plus active membership. Membership administration requires `membership.manage`; invitation administration requires `invitations.manage`; both remain active-Alliance scoped and use required Identity assurance for privileged HTTP mutations.

Self-leave uses a dedicated workflow. Administration also respects effective role hierarchy and last-active-Owner safety in addition to permissions.

## 4. Tenant and privacy boundaries

Each membership belongs to exactly one User and one Alliance. Submitted membership/invitation identifiers are re-resolved under the active Alliance; one Alliance's membership or invitation state grants nothing in another.

Recruitment may request onboarding through the supported invitation contract but never receives direct authority over Memberships persistence.

## 5. Trust boundaries and data flows

Material flows include authenticated tenant manager → membership administration, authenticated user → self-leave, privileged manager/Recruitment handoff → invitation issuance, invitation bearer link + authenticated intended account → acceptance, and membership state → Alliances/Authorization tenant access decisions.

## 6. Threats, abuse cases and controls

Threats include stale/suspended membership retaining access, hidden privileged roles returning after reactivation, last Owner removal, peer/owner administration, cross-Alliance membership mutation, invitation theft/replay/wrong-account acceptance, multiple valid replacement invitations, and direct Recruitment membership creation.

Controls include active-status revalidation, role stripping on leave/removal, safe Member role restoration, effective-rank checks, last-Owner guard, tenant-scoped re-resolution, token hashing/expiry/email binding/one-time use, serialized replacement, and Recruitment handoff through the invitation contract.

## 7. Integrity, concurrency and idempotency

Membership transitions use supported state rules rather than arbitrary status edits. Leave/removal strips roles; reactivation with no roles restores only built-in Member. Owner safety is checked before changes that could remove the final active Owner.

Invitation issuance/replacement/acceptance uses locking/transactions where required so concurrent requests do not preserve multiple valid bearer paths or create duplicate membership transitions.

## 8. Secrets and credential handling

Memberships owns invitation bearer-token verifier state but no password/MFA/API/webhook credentials. Invitation tokens are generated with strong entropy, stored only in non-replayable verifier form, rotated on replacement/resend, and never copied into audit/log/outbox payloads.

Recipient email is personal data and is used to bind acceptance to the intended authenticated account.

## 9. Destructive operations, retention and deletion

Leave/removal changes access and strips role assignments but preserves supported history rather than erasing evidence. Invitation revocation/expiry invalidates access material without inventing membership state.

Platform account/Alliance lifecycle may orchestrate broader cleanup/anonymization while Memberships retains authority over membership/invitation semantics and evidence obligations.

## 10. Auditability, observability and evidence

Membership/invitation transitions are attributable where required, including replacement/revocation behavior. Operators diagnose status, hierarchy, Owner safety, tenant context, invitation status/expiry/email binding, and Platform lifecycle/capacity separately.

Tests protect status transitions, role strip/restore, cross-tenant isolation, hierarchy/Owner safety, invitation abuse/concurrency, and Recruitment handoff. See [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Email-channel compromise can expose an invitation link before application controls see it; one-time/expiry/email-binding reduce but do not eliminate that external risk. User mailbox/device security is outside repository evidence.

Memberships does not authenticate Users, define permission vocabulary, grant Platform access, infer access from game identity, or expose invitations as non-secret public links.

## 12. Focused reviews and related documentation

- [Invitations security review](invitations-security-review.md)
- [Membership invitations contract](../invitations.md)
- [Identity security profile](../../identity/security/README.md)
- [Authorization security profile](../../authorization/security/README.md)
- [Recruitment security profile](../../recruitment/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 1 threat model](../../../security/phase-1-threat-model.md)
