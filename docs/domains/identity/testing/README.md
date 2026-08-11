# Identity testing and evidence

[← Identity domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Identity  
**Code owner:** `app/Domain/Identity`  
**Primary validation boundary:** Authentication, recovery, verified/password-confirmed/MFA assurance, session security, secret handling, and account-deletion handoff  
**P5 evidence decision:** Living suite map with Phase 1 and Platform lifecycle evidence reused

## 1. Critical claims and validation ownership

Identity validation must prove login/registration/recovery/session behavior, email verification, recent password confirmation, MFA enrollment/challenge/recovery/downgrade protection, secret non-disclosure and the supported account-deletion handoff into Platform lifecycle.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. No dedicated Identity performance SLA is accepted; rate-limit behavior is a Feature/security contract rather than a throughput target.

## 3. Architecture and domain-boundary validation

Architecture evidence protects global User/Identity ownership and the separation among authentication, Alliances tenant context, Memberships relationship state, Authorization permissions and Platform administrator grants.

It also protects secret-bearing MFA/recovery behavior from cross-domain reach-through.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration evidence covers guest/authenticated transitions, verified-email gates, password confirmation, MFA challenge/recovery, other-session invalidation and sensitive-operation assurance. TenantIsolation evidence matters when authenticated identity is combined with active-Alliance feature access.

[Identity security](../security/README.md) and [MFA and recovery](../mfa-and-recovery.md) define secret/token/privacy claims that tests must preserve.

## 5. Feature, interface and integration validation

Feature coverage owns registration/login/logout/reset/verification/profile/password/session/MFA/account-deletion routes. Integration evidence covers mail/token/session persistence and Platform deletion handoff where applicable.

[Identity interfaces](../interfaces/README.md) remains the current surface inventory.

## 6. Idempotency, concurrency and asynchronous validation

Recovery/MFA one-time material must not be reusable after successful consumption. Account-deletion handoff is coordinated through persisted lifecycle state; retries may not bypass Platform blockers such as ownership/legal hold/Platform-admin status.

Authentication does not become successful merely because a notification/mail job was queued.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 1 exit report](../../../product/phase-1-exit-report.md) records foundational User/session/MFA/invitation/tenant migration and protected recovery evidence. Current CI continues clean PostgreSQL migration and database backup/restore.

Identity encrypted-state recovery additionally depends on correct application key material as documented in [Identity operations](../operations/README.md); database restore alone is not sufficient evidence for encrypted secret recoverability.

## 8. Performance, query and capacity evidence

Identity has accepted authentication/recovery throttles but no general performance SLA/query budget. Throttle semantics are validated at Feature/security level.

## 9. Accessibility and frontend evidence

Registration/login/recovery/profile/MFA flows were included in [Phase 1 accessibility review](../../../product/phase-1-accessibility-review.md). Current `npm run check` protects frontend code quality, not deployment-specific accessibility conformance.

## 10. Historical accepted evidence

Primary evidence is [Phase 1 exit report](../../../product/phase-1-exit-report.md). [Phase 6 exit report](../../../product/phase-6-exit-report.md) adds Platform/account-deletion administration separation and P5-recovered immutable Phase 6 run identities.

## 11. Evidence identity, retention and supersession

Phase 1/6 SHAs/check IDs remain historical. Current Identity suite mapping follows current code/tests.

Future Identity acceptance records must preserve exact revision/workflow identity under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Identity has no OAuth/delegated external-token contract, no Alliance permission role, and no claim that a database backup alone recovers encrypted state. No standalone Identity latency SLA is accepted.

Related documentation:

- [Identity domain](../README.md)
- [MFA and recovery](../mfa-and-recovery.md)
- [Identity security](../security/README.md)
- [Identity operations](../operations/README.md)
- [Identity interfaces](../interfaces/README.md)
- [Platform testing](../../platform/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
