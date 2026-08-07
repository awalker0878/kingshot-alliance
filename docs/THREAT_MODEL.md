# Kingshot Alliance Threat Model

**Current review:** Phase 1 — Identity and Multi-Tenancy  
**Reviewed:** 2026-08-07  
**Status:** Phase 1 acceptance candidate

## Purpose

This document records the security threats and controls that apply to the current delivered architecture. It is updated at each phase gate and focuses on abuse paths that could compromise global user identity, alliance isolation, authorization, auditability, or asynchronous side effects.

## Phase 1 system boundary

The reviewed Phase 1 path is:

1. Browser requests reach the Nginx/Laravel application boundary established in Phase 0.
2. Laravel authenticates the global user and resolves an explicit active-alliance context from the authenticated session.
3. Alliance-owned operations authorize against an active membership and alliance-scoped role permissions.
4. PostgreSQL stores users, alliances, memberships, roles, permissions, invitations, audit events, and transactional outbox messages.
5. Redis provides hosted sessions, cache, queues, locks, and rate-control infrastructure.
6. The outbox publisher claims persisted domain events with database locking and publishes a stable idempotency key for retry-safe downstream handling.

Phase 1 does not yet expose content uploads, reports/exports, webhooks, game-data integrations, or platform-administrator workflows. Those surfaces require new threat-model review when introduced.

## Assets

- Account credentials, password-reset state, and verified email identity.
- Encrypted TOTP secrets and hashed one-time recovery codes.
- Authenticated sessions and active-alliance selection.
- Invitation bearer tokens and invitation lifecycle state.
- Alliance membership state and role assignments.
- Alliance-scoped permissions and the last-active-owner invariant.
- Alliance-owned data and tenant context carried into later cache, job, export, log, and storage work.
- Audit events and transactional outbox records.
- Request IDs and trace IDs used for operational correlation.

## Trust boundaries

- **Anonymous browser → application:** registration, login, password reset, invitation lookup, and MFA challenge are untrusted input surfaces.
- **Authenticated browser → alliance boundary:** a valid global identity does not imply membership in the selected alliance.
- **Application → PostgreSQL/Redis:** application authorization must complete before tenant-owned state changes; persistence constraints are defense in depth, not the primary authorization layer.
- **Request → asynchronous work:** tenant identity must be serialized explicitly rather than inferred from mutable process-global state.
- **Outbox → future consumers:** publication is at-least-once; consumers must use the supplied idempotency key.

## Threats and controls

| Threat | Abuse scenario | Phase 1 controls | Verification / evidence | Residual risk |
|---|---|---|---|---|
| Cross-alliance IDOR | A member submits another alliance's membership, invitation, role, or alliance identifier. | Explicit active-alliance middleware; alliance-scoped queries; scoped route identifiers; authorization service requires active membership. | `AllianceIsolationTest`, `ActiveAllianceHttpTest`, `MembershipAdministrationTest`, `InvitationLifecycleTest`. | Every future tenant-owned model must adopt the same scoping pattern. |
| Stale or confused tenant context | A session keeps an alliance selection after membership is suspended/removed, or downstream work uses the wrong alliance. | Context is resolved per request from active membership and cleared after the request; stale session selection is removed; `TenantContextSnapshot` serializes alliance/user/request/trace identity and provides tenant-safe cache/storage/export helpers. | `ActiveAllianceHttpTest`, `TenantContextSnapshotTest`. | Future jobs/exports must require the snapshot rather than reconstructing tenant context implicitly. |
| Role escalation | A member or leader grants themselves/others a higher role or manages a peer/owner outside authority. | `roles.manage` is reserved to the owner template; membership administration uses rank checks; cross-alliance role IDs are rejected. | `MembershipAdministrationTest`. | Custom role-template editing is not exposed in Phase 1; if introduced later it requires dedicated permission-diff authorization and audit. |
| Hidden privilege residue | Roles remain attached to a removed/left account, or are assigned while membership is inactive and become effective on reactivation. | Leave/removal detaches roles; role assignment now requires an active membership; reactivation with no roles receives only the member role. | `MembershipAdministrationTest` repeat/removal regressions. | Suspended memberships intentionally retain their existing roles but authorization denies them while suspended. |
| Loss of alliance ownership | The final active owner leaves, is suspended/removed, or loses the owner role. | Owner-state changes verify another active owner exists before proceeding. Ownership transfer is performed by adding another owner before the original owner leaves/removes the role. | `MembershipAdministrationTest`. | A future dedicated ownership-transfer UI should keep the same transaction and invariant. |
| Invitation theft or replay | An attacker guesses, steals, reuses, or submits an invitation for a different email/alliance. | 256-bit random bearer tokens; only SHA-256 token hashes are stored; tokens are email-bound, expiring, single-use by status, alliance-scoped, and rotated on resend. | `InvitationLifecycleTest`, `InvitationReplacementTest`. | Bearer tokens remain sensitive until redeemed/expired; notification delivery must avoid logging them when added later. |
| Duplicate / replacement invitation abuse | Multiple pending tokens remain valid for the same email because of repeat or concurrent issuance. | Invitation creation locks the alliance row to serialize issuance, revokes prior pending invitations, and records both audit and outbox revocation events before creating the replacement. | `InvitationReplacementTest`. | Multi-node correctness depends on PostgreSQL row locking, which is the hosted database baseline. |
| Registration-mode bypass | An attacker creates an account when the deployment is invitation-only. | Registration mode is validated server-side; invitation-only registration requires a valid pending token whose email matches the account being created, and user creation plus invitation acceptance is transactional. | `InvitationLifecycleTest`. | Open-registration deployments intentionally allow account creation without alliance membership. |
| Credential stuffing / brute force | Repeated login, registration, or MFA attempts are automated. | Named rate limits protect registration, login, and MFA challenge; password-reset and verification endpoints are throttled. | Route configuration and authentication feature tests. | Rate thresholds require production telemetry tuning as traffic patterns emerge. |
| Session fixation / stale authenticated sessions | An attacker retains a pre-authentication session identifier or old sessions survive credential changes. | Session regeneration on login, MFA completion, registration, and logout; `auth.session`; password change and explicit other-session revocation invalidate other authenticated sessions. | `AuthenticationTest`, `ProfileManagementTest`, `AccountLifecycleTest`, MFA feature tests. | Compromised devices remain a user operational risk until revoked or session expiry occurs. |
| Password-reset / email-verification abuse | Reset or verification links are forged, replayed, or used for another account. | Laravel signed verification URLs, hashed reset-token handling, throttling, email identity checks, and session regeneration after credential changes. | Account lifecycle feature tests. | Email account compromise is outside the application trust boundary. |
| MFA secret or recovery-code disclosure | Database/API/UI exposure leaks the second factor. | TOTP secret and recovery-code arrays use encrypted model casts and are hidden from serialization; recovery codes are individually SHA-256 hashed inside the encrypted array and displayed only on creation/regeneration; MFA management requires verified authentication plus recent password confirmation. | `TwoFactorAuthenticationTest`, `TotpServiceTest`, route review. | TOTP codes are time-window based and can be reused within their validity window; stronger phishing-resistant factors are deferred. |
| MFA bypass during login | Password authentication establishes a full session before the second factor. | A confirmed-MFA user is logged out after password verification and receives only a challenge marker; full authentication is re-established only after valid TOTP or one-time recovery code. | `TwoFactorAuthenticationTest`. | Recovery codes must be stored securely by the user. |
| Audit repudiation | Privileged changes occur without actor, tenant, subject, or request correlation. | Authentication, alliance creation, invitations, membership state, role assignment/removal, MFA changes, and other privileged Phase 1 mutations record audit events with actor/subject/alliance and request/trace correlation where applicable. | Feature tests plus `AuditRecorder`. | Audit immutability/retention policy will require additional operational controls as platform administration matures. |
| Lost or duplicated asynchronous side effects | A transaction commits but notification/integration work is lost, or retries create duplicates. | Meaningful persisted changes create outbox rows inside the same transaction; publisher uses row locks, bounded retry/backoff, stable per-event idempotency keys, publication timestamps, attempts, and error diagnostics. Repeat membership transitions now generate distinct event keys while duplicate no-op role assignment emits no event. | `OutboxPublisherTest`, membership repeat-transition regressions, staging/recovery CI. | Delivery is at-least-once; all future consumers must enforce idempotency. |
| Log / trace data leakage | Sensitive tokens, credentials, or uncontrolled paths appear in logs. | Phase 0 redaction/logging baseline remains in force; Phase 1 audit metadata excludes raw passwords, MFA secrets, recovery codes, and invitation tokens. | Security baseline and code review. | Future notification/integration payload logging requires dedicated review. |

## Authorization model decision

Phase 1 delivers fixed, system-defined alliance role templates and their permission grants. It exposes audited membership role assignment/removal, not arbitrary editing of permission definitions or role templates. `alliance.manage` and later-domain permissions are intentionally provisioned now so later phases can authorize new operations without changing the identity model.

If custom role or permission-template editing is introduced later, it must add:

- explicit owner-only or stronger authorization;
- transactional permission-diff persistence;
- prevention of self-lockout and last-owner privilege loss;
- before/after audit metadata;
- transactional outbox events;
- cross-alliance and privilege-escalation tests.

## Deferred surfaces requiring renewed review

- Public/member content and file uploads (Phase 2).
- Event reminders, recurring schedules, notification delivery, and exports (Phase 3+).
- Recruitment applicant personal data and retention (Phase 4).
- Contribution reporting, large exports, and scheduled reports (Phase 5).
- Platform administration, API credentials, webhooks, support impersonation, alliance deletion/export, and billing foundations (Phase 6).

## Review triggers

Update this threat model whenever a phase adds a new trust boundary, authentication factor, authorization primitive, tenant-owned persistence model, queue consumer, notification channel, export/storage path, external integration, or platform-administrator capability.
