# Membership invitations security review

[← Memberships security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Memberships  
**Capability:** Membership invitations  
**Code owner:** `app/Domain/Memberships`

## 1. Scope and security objective

Protect Alliance invitation issue, resend/rotation, revoke, expiry, and acceptance as an expiring email-bound bearer-access lifecycle. The capability must create/reactivate membership only for the intended authenticated verified User and must prevent replay or multiple concurrently valid replacement links.

## 2. Assets and sensitive data

Assets include Alliance/recipient email binding, invitation status/expiry/creator metadata, protected token verifier state, one-time issued bearer material, and the resulting membership/Member-role transition.

Bearer material is secret. Recipient email is personal data and is used as an authorization binding during acceptance.

## 3. Trust boundaries

- Privileged Alliance manager/Recruitment handoff → invitation issuance.
- Application → secure bearer generation/protected verifier persistence.
- Email/external delivery channel → applicant/User possession of bearer link.
- Authenticated verified User + bearer → invitation acceptance.
- Acceptance transaction → Memberships + Authorization role restoration + Audit/outbox evidence.

The mailbox/device channel is outside repository control.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Database leak reveals usable invitation links | Unauthorized membership | Only protected verifier state persisted; plaintext bearer not recoverable. |
| Stolen link accepted by wrong account | Unauthorized membership | Acceptance requires authenticated verified User with matching normalized email. |
| Expired/revoked/accepted link replay | Unauthorized/duplicate membership | State + expiry + one-time acceptance checks fail closed. |
| Resend keeps old bearer valid | Multiple attack paths | Resend rotates bearer material; prior material becomes invalid. |
| Concurrent issue creates multiple pending links | Multiple valid bearers | Same-Alliance/email issuance serialized; earlier pending invitations revoked. |
| Active member reinvited | Confusing duplicate access state | Active-member duplicate rejected. |
| Capacity/lifecycle bypass through direct creation | Tenant overrun/invalid access | Platform capacity/lifecycle checks are part of supported issue path. |
| Recruitment directly creates membership | Boundary bypass | Recruitment uses Memberships invitation contract only. |
| Token leaks through logs/analytics/docs | Credential disclosure | Bearer excluded from routine logs/audit/outbox/public content/documentation. |

## 5. Authorization, tenancy and privacy

Issue/revoke/resend requires active Alliance context and `invitations.manage` plus applicable recent password confirmation. Invitation state belongs to one Alliance/email.

Acceptance is intentionally a compound boundary: possession of the secret bearer is insufficient without the matching authenticated/verified account. Membership activation still occurs only within the invitation's Alliance.

## 6. Integrity, replay and concurrency

Issuance serializes same-Alliance/email pending state. Acceptance is transactional with membership create/reactivation, Member-role restoration when required, invitation consumption, and evidence so partial success cannot leave accepted token state without the corresponding access transition.

Repeated acceptance cannot create duplicate active memberships. Revoked/accepted invitation cannot be resent into a valid state.

## 7. Secret and data lifecycle

Bearer material is generated with strong entropy, shown/delivered only through the controlled issue/resend flow, and stored only as verifier state. Resend rotates the secret rather than recovering it. Expiry/revocation/acceptance ends usability.

Logs, audit, traces, analytics, exports, URLs copied into public documentation, and generic outbox payloads must not retain bearer values.

## 8. Abuse limits and failure behavior

Invalid/malformed/expired/revoked/consumed token, email mismatch, inactive target lifecycle/capacity denial, or active-member duplicate all fail closed without creating membership.

Operators diagnose using safe invitation ID/status/expiry/email-normalization/member/capacity metadata, never by printing the token.

## 9. Verification and evidence

Tests cover protected verifier persistence, issue/expiry/revoke/resend/acceptance, same-email serialization, active-member duplicate rejection, email normalization/mismatch, capacity/lifecycle checks, transactional membership activation/reactivation, role restoration, and Recruitment handoff.

Shared policy: [Security baseline](../../../../security/security-baseline.md). Historical source: [Phase 1 threat model](../../../../security/phase-1-threat-model.md).

## 10. Residual risks and external controls

A compromised recipient mailbox/device can expose a still-valid bearer before application controls detect misuse. Expiry, one-time use, rotation, verified matching account, and revocation reduce that risk but cannot secure the external mail channel.

No public recoverable invitation directory, anonymous token-only membership creation, or direct Recruitment persistence bypass is accepted.