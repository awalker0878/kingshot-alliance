# Identity interfaces

[← Identity domain](../README.md)

**Document type:** Living domain interface profile  
**Status:** Current  
**Owning domain:** Identity  
**Code owner:** `app/Domain/Identity`  
**Primary boundary:** Global user authentication, recovery, verification, session/profile security, MFA, and assurance consumed by feature domains  
**P4 inventory decision:** Focused contract reused — `../mfa-and-recovery.md`

## 1. Boundary purpose and ownership

Identity owns the global application-user and authentication/assurance boundary. It proves who a caller is and exposes verified-email, password-confirmation, session, and MFA assurance consumed by sensitive feature-domain workflows.

Identity does not establish Alliance tenancy or grant Alliance/Platform authorization. Those checks remain separate callers of Identity assurance.

## 2. Surface inventory

Guest/anonymous-compatible first-party surfaces in `routes/web.php` include:

- registration GET/POST;
- login GET/POST;
- two-factor login challenge GET/POST;
- forgot-password request; and
- password-reset GET/POST.

Authenticated surfaces include logout, profile read/update, password update, other-session invalidation, email-verification notice/verification/resend, and password confirmation.

Verified + recently password-confirmed users may begin/confirm/disable MFA and regenerate recovery codes. `routes/account.php` adds authenticated verified account-deletion view/request, with password confirmation on the request mutation.

## 3. Callers, authorization and tenancy

Guest authentication/recovery routes operate on global Identity rather than an Alliance tenant. Successful authentication still does not grant tenant access.

Feature-domain callers consume Identity assurance through framework/session/User state: authenticated User, verified email, recent password confirmation, and MFA where required. Platform administration additionally requires the separate Platform grant; Alliance feature work additionally requires active Memberships/Authorization.

Rate limits include login 5/minute by normalized email+IP, registration 3/minute by email+IP, and two-factor challenge 5/minute by challenge-user identity+IP. Password reset/verification routes also use their declared throttles.

## 4. Input and validation contracts

Registration/login/recovery/profile/password routes validate their supported account fields and framework authentication requirements. Password-reset links/tokens follow the framework-backed recovery contract rather than feature-domain tokens.

MFA enrollment/challenge/recovery-code input and one-time semantics are documented in [MFA and recovery](../mfa-and-recovery.md). Other domains consume assurance outcomes, never the MFA secret/recovery material.

## 5. Output and disclosure contracts

Authentication surfaces return first-party pages/redirects/status without exposing password hashes, session secrets, MFA secret material, or recovery-code storage.

Feature domains receive only the User identity and required assurance state. Identity does not serialize a public external user profile/OAuth representation.

## 6. Internal actions, queries and services

Supported internal contracts include account registration/authentication/recovery/profile/session-security actions and the User assurance state consumed by other domains.

Account-deletion requests cross into Platform lifecycle orchestration through the supported Identity/Platform contract. Feature domains must not infer verified/password/MFA assurance by directly inspecting secret persistence outside the accepted Identity model/services.

## 7. Events, outbox and cross-domain consumers

Security-relevant account changes may create safe Audit/Platform evidence. Identity events do not automatically define a public identity webhook, OAuth callback, or external account API.

Account deletion is coordinated with Platform legal-hold/lifecycle processing; producer/owner semantics remain explicit rather than driven by generic outbox fan-out.

## 8. Commands, jobs and scheduled work

Identity has no standalone authentication scheduler command. Account deletion processing is exposed through Platform's `platform:process-account-deletions {--limit=100}`, scheduled hourly, because Platform owns the cross-domain destructive lifecycle orchestration.

Authentication, verification, session and MFA assurance are otherwise request driven.

## 9. Files, imports, exports and external dependencies

Identity has no direct file import/export interface. Its material dependencies include session/cookie framework state, mail delivery for verification/reset notifications where configured, application encryption key material, and PostgreSQL/Redis according to runtime configuration.

MFA secret/recovery persistence and APP_KEY recovery constraints are documented in Identity security/operations, not exposed as file contracts.

## 10. Failure, idempotency, versioning and compatibility

Invalid credentials, unverified email, stale password confirmation, invalid/used recovery material, or missing MFA assurance fail closed for workflows that require them. Account recovery uses supported token/Identity workflows rather than direct data edits.

Route names and assurance middleware are first-party compatibility contracts used across the application. There is no accepted external Identity API version/OAuth compatibility promise.

## 11. Explicit non-capabilities

Identity does not:

- grant Alliance membership/roles/permissions;
- establish active Alliance context;
- grant Platform administration;
- expose OAuth/user-delegated external tokens;
- expose a public user-management API; or
- disclose password/session/MFA/recovery secrets to feature domains, Audit, outbox, or webhooks.

## 12. Focused contracts, evidence and related documentation

P4 reuses [MFA and recovery](../mfa-and-recovery.md) for the secret-bearing assurance interface.

Related documentation:

- [Identity domain](../README.md)
- [MFA and recovery](../mfa-and-recovery.md)
- [Identity security](../security/README.md)
- [Identity operations](../operations/README.md)
- [Platform lifecycle and retention](../../platform/lifecycle-and-retention.md)
- [Interface documentation standard](../../../product/interface-documentation-standard.md)
- [P4 interface coverage matrix](../../../product/interface-coverage-matrix.md)
