# MFA and recovery security review

[← Identity security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Identity  
**Capability:** TOTP MFA and recovery codes  
**Code owner:** `app/Domain/Identity`

## 1. Scope and security objective

Protect TOTP enrollment/challenge and one-time recovery codes as global User authentication-assurance material. The capability must strengthen privileged authentication without becoming a substitute for Alliance membership/permission or Platform grants.

## 2. Assets and sensitive data

Assets include MFA enrollment/confirmation state, TOTP secret/configuration, recovery-code set/state, challenge result/session assurance, and security-administration timestamps.

TOTP secrets and recovery codes are high-sensitivity authentication secrets. Enrollment/assurance status is sensitive account-security metadata but is not itself a tenant permission.

## 3. Trust boundaries

- Verified authenticated User + recent password → MFA administration.
- User authenticator/recovery material → MFA challenge verifier.
- Protected MFA persistence → assurance result exposed to the authenticated session.
- Identity assurance result → Platform/other sensitive feature routes.

Raw secret/recovery values do not cross into business-domain persistence, audit, outbox, exports, URLs, or routine telemetry.

## 4. Threats and controls

| Threat | Security impact | Current controls |
| --- | --- | --- |
| Attacker enrolls/replaces MFA from stolen session | MFA takeover/downgrade | Verified identity + recent password required; confirmed MFA is not silently overwritten by enrollment. |
| TOTP/recovery secret stored or serialized plaintext | Account compromise | TOTP secret protected with encrypted storage/casts and excluded from serialization; recovery values persisted only in protected non-replayable form. |
| Recovery code replay | MFA bypass | One-time consumption and concurrency-safe update. |
| Old recovery codes survive rotation | Continued unauthorized access | Regeneration invalidates prior recovery material according to supported flow. |
| Session fixation after successful MFA | Session takeover | Successful challenge regenerates/strengthens the authenticated session boundary as implemented by Identity flow. |
| Brute-force challenge | Account compromise/resource abuse | Separate MFA challenge throttling and fail-closed verification. |
| MFA result interpreted as Alliance/Platform authority | Privilege escalation | Explicit separation: owning membership/permission or Platform grant remains mandatory. |

## 5. Authorization, tenancy and privacy

MFA is global User state and is not Alliance scoped. MFA administration is limited to the authenticated User with required verification/password assurance.

Feature domains may consume only the fact that required MFA assurance is satisfied. They must not read TOTP secrets/recovery material and must still apply their own tenant/permission/grant rules.

## 6. Integrity, replay and concurrency

Partial enrollment is not equivalent to confirmed MFA. A consumed recovery code cannot become valid again. Concurrent attempts against one recovery code must result in at most one successful consumption.

Invalid challenge never falls back to role/member state. Direct persistence changes are not an accepted recovery path.

## 7. Secret and data lifecycle

Enrollment creates protected TOTP configuration; the user configures their authenticator through the controlled UI flow. Recovery values are displayed only when created/regenerated and then cannot be recovered from normal persistence.

Rotation replaces prior recovery material. Disabling/resetting MFA, if supported by the current Identity workflow, requires the documented assurance boundary and must not leak prior secret material.

## 8. Abuse limits and failure behavior

Challenge attempts are throttled. Invalid TOTP/recovery input fails closed and does not reveal secret state. Recovery exhaustion or lost authenticator routes users through supported account-security recovery rather than administrative database bypass.

Logs/telemetry may record safe outcome/status/correlation fields but never secret values.

## 9. Verification and evidence

Verification includes enrollment/confirmation, password-confirmation prerequisite, RFC-compatible TOTP success/failure, challenge throttling/session behavior, one-time recovery consumption, recovery rotation, secret exclusion from serialization/log/audit paths, and separation from Alliance/Platform authorization.

Historical source: [Phase 1 threat model](../../../../security/phase-1-threat-model.md). Shared controls: [Security baseline](../../../../security/security-baseline.md).

## 10. Residual risks and external controls

TOTP does not protect a fully compromised endpoint/authenticator and is phishable compared with phishing-resistant authenticators. WebAuthn/external IdP is not currently implemented. User storage of recovery codes and device security are outside repository control.

Production session/HTTPS/secret-key protection remains shared infrastructure evidence and is required for encrypted MFA state to provide its intended protection.