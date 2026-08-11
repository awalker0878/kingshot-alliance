# Memberships operations profile

[← Memberships domain](../README.md) · [Shared operations](../../../operations/README.md)

**Document type:** Living domain operations profile  
**Status:** Current  
**Owning domain:** Memberships  
**Code owner:** `app/Domain/Memberships`  
**Primary operational boundary:** membership lifecycle and invitation issue/revoke/resend/acceptance with email-bound bearer semantics

## 1. Operational purpose and runtime shape

Memberships owns synchronous membership and invitation lifecycle transitions. It has no dedicated scheduler or queue worker in the accepted runtime. Invitation delivery depends on the configured mail transport, but acceptance state is durable PostgreSQL state.

## 2. Persistent state and ownership

Durable state includes Alliance memberships, lifecycle status, invitation records, recipient binding, expiry/status and protected token verifier state. Authorization owns role semantics; Recruitment may request onboarding only through the Memberships invitation contract.

## 3. Configuration and runtime dependencies

Memberships depends on PostgreSQL, active tenant context, Authorization, `INVITATION_TTL_HOURS`, and configured mail delivery for invitation messages. Real SMTP deliverability is an external production dependency and is not proven by repository readiness.

## 4. Normal flow and background processing

Managers issue/revoke/resend invitations synchronously. Resend rotates bearer material. Acceptance requires the matching authenticated verified account and transactionally creates/reactivates membership plus supported role restoration while consuming the invitation.

## 5. Health, observability and diagnostics

Inspect invitation ID, Alliance, normalized recipient email, status, expiry, creator, membership state and related audit/request/trace IDs. For mail issues, distinguish successful invitation persistence from actual external message delivery.

## 6. Failure modes and diagnosis

Common failures are active-member duplicate, expired/revoked/consumed invitation, email mismatch, capacity/lifecycle denial, cross-tenant state, mail transport failure or PostgreSQL failure. A missing email does not imply the invitation database transaction failed.

## 7. Recovery, replay and reconciliation

If mail delivery failed but the invitation remains valid, use the supported resend flow; it rotates the bearer and invalidates prior material. Do not recover or reuse the old plaintext token. Repeated acceptance must not create duplicate active memberships.

## 8. Backup, restore, migration and rollback

Membership and invitation state is PostgreSQL-backed. After restore verify representative active/inactive memberships, role linkage and invitation state/expiry. Previously delivered bearer links may exist outside the database; restoring older invitation state requires explicit security review before allowing acceptance/resend.

## 9. Capacity, query and performance boundaries

Membership/invitation operations are tenant-bounded and concurrency-protected. Alliance capacity limits are business/platform invariants; repository tests do not define production onboarding throughput.

## 10. External-service degradation

Mail degradation can prevent users from receiving new invitation links while persisted invitation state remains. Restore mail service and resend through the supported rotation path; do not weaken recipient/email verification or expose bearer values through support channels.

## 11. Safe operator actions and stop conditions

Safe actions are inspect safe invitation metadata, restore mail/PostgreSQL, resend/revoke through supported manager flows and verify resulting membership state. Stop if recovery would require printing/recovering token plaintext, bypassing email match, capacity/lifecycle, tenant boundaries or direct membership creation from Recruitment.

## 12. Evidence, focused runbooks and related documentation

Retain safe invitation/membership IDs, status/expiry, request/trace IDs, mail provider incident/change identifier and release SHA. No focused P3 Memberships runbook is required. See [configuration](../../../operations/configuration-reference.md), [incident response](../../../operations/runbooks/incident-response.md), and the [Memberships security profile](../security/README.md).
