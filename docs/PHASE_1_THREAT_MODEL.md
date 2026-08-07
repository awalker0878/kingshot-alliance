# Phase 1 Threat Model — Identity and Multi-Tenancy

**Phase:** 1 — Identity and Multi-Tenancy  
**Status:** Reviewed for phase exit  
**Reviewed:** 2026-08-07  
**Scope:** Authentication, account lifecycle, alliance tenancy, memberships, roles, invitations, MFA, audit, and outbox publication.

## Security objectives

1. A global user identity must never gain access to an alliance without an active membership and an authorized role.
2. Alliance-owned data and actions must remain isolated even when one user belongs to multiple alliances.
3. Authentication and recovery flows must resist account enumeration, session fixation, credential replay, MFA bypass, and recovery-code reuse.
4. Privileged membership, invitation, role, and MFA changes must be attributable and require appropriate re-authentication.
5. Persisted domain changes and their audit/outbox records must remain transactionally consistent and safe to retry across legitimate repeat state transitions.

## Assets

- User credentials, verified email state, sessions, remember tokens, personal access tokens, MFA secrets, and recovery codes.
- Alliance records, active-alliance selection, memberships, membership status, roles, and permissions.
- Invitation tokens and invitation lifecycle state.
- Audit events, request/trace correlation, and transactional outbox messages.
- Tenant identifiers carried into request, cache, job, storage, export, and logging contexts.

## Trust boundaries

- **Unauthenticated browser → authentication surface:** registration, login, password recovery, email verification, invitation links, and MFA challenge.
- **Authenticated browser → global identity surface:** profile, password, session controls, and MFA administration.
- **Authenticated browser → alliance surface:** active-alliance middleware establishes the tenant boundary before alliance-scoped actions execute.
- **Application → PostgreSQL:** authoritative identity, membership, role, invitation, audit, and outbox state with foreign-key and uniqueness constraints.
- **Application → Redis:** session, cache, queue, rate-limit, and coordination state; tenant-specific application keys must include an explicit tenant snapshot.
- **Request transaction → asynchronous consumers:** outbox messages cross from persisted domain state to at-least-once event publication and therefore require idempotency.

## Threats and controls

| Threat | Impact | Primary controls | Verification |
| --- | --- | --- | --- |
| Cross-alliance route or identifier substitution | Read/write access to another alliance | Active membership lookup by `alliance_id` + `user_id`; alliance-scoped queries; no unsafe global route binding for activation; composite tenant foreign keys | `AllianceIsolationTest`, `ActiveAllianceHttpTest`, invitation and membership tests |
| Cross-alliance role injection | Privilege escalation | Composite `membership_roles` foreign keys enforce matching alliance IDs; role queries include alliance ID; owner-only role administration | Adversarial role-pivot test and membership administration tests |
| Stale/suspended membership retains access | Unauthorized continued access | Active-alliance middleware revalidates `active` membership each request and clears invalid session context | Suspended/stale context HTTP tests |
| Hidden role residue on inactive membership | Removed/left account regains a privileged role on reactivation | Leave/removal strips roles; role assignment requires an active membership; reactivation with no roles receives only the member role | `MembershipAdministrationTest` inactive-role and reactivation regressions |
| Last owner loses ownership | Alliance takeover or administrative lockout | Last-owner safety prevents leave, suspension/removal, or owner-role removal until another active owner exists | Ownership transfer / last-owner membership tests |
| Invitation theft, replay, wrong-account acceptance | Unauthorized membership | 256-bit random bearer token stored only as SHA-256 hash; expiry/status checks; email binding; row locks; token rotation on resend; one-time acceptance/revocation | Invitation lifecycle abuse tests |
| Duplicate or replacement invitations remain valid | Multiple bearer tokens for the same email survive repeat/concurrent issuance | Alliance-row lock serializes creation; prior pending invitations are revoked before replacement; superseded revocations are audited and emitted through the outbox | `InvitationReplacementTest` plus invitation lifecycle tests |
| Session fixation at login or MFA completion | Account/session takeover | Session ID regeneration after password authentication and after successful MFA; logout invalidates session and CSRF token | Authentication and MFA feature tests |
| Account enumeration through login/reset | Privacy and targeting | Generic credential errors; password-reset request does not disclose account existence; normalized email handling | Authentication/account lifecycle tests |
| Password-reset or profile change leaves stale API/session access | Unauthorized persistence | Password changes/reset revoke personal access tokens; password change invalidates other sessions; `auth.session` enforces password-hash changes | Account/profile tests |
| MFA setup overwrites an already-enabled factor | MFA downgrade | Enrollment refuses to replace confirmed MFA; MFA administration requires verified email and recent password confirmation | MFA downgrade regression test; route review |
| TOTP/recovery bypass or replay | Authentication bypass | RFC 6238 TOTP verification; encrypted secret; hashed recovery codes; one-time recovery-code consumption; challenge throttling | RFC vector/unit tests and MFA HTTP tests |
| Privileged alliance mutation from a stolen but old session | Membership/role takeover | Verified-email requirement plus recent password confirmation for invitation, membership, role, and leave operations | Account lifecycle/password-confirmation test; route review |
| Role escalation or peer/owner administration | Alliance takeover | Membership hierarchy guard; owner-only role management permission; last-owner safety; alliance-scoped role lookup | Membership administration tests |
| Repeated legitimate role/leave transitions collide in outbox | Valid state transition rolls back because an earlier event reused the same idempotency key | Duplicate role assignment is a no-op; each real role assignment and leave event receives a distinct per-event key while publisher retries retain that row's stable key | Membership repeat-transition regressions and `OutboxPublisherTest` |
| Outbox loss after database commit | Missing downstream side effects | Outbox record is written in the same database transaction as meaningful persisted changes | Domain service tests and outbox publisher tests |
| Duplicate outbox delivery | Repeated downstream effect | At-least-once publication with a stable per-message idempotency key propagated to consumers; published timestamp after dispatch | Outbox publisher idempotency test |
| Concurrent outbox workers double-claim | Duplicate amplification | PostgreSQL `FOR UPDATE SKIP LOCKED`; lease through `available_at`; attempt counter; single-server/overlap scheduler guard | Publisher tests plus PostgreSQL CI/staging path |
| Downstream publication failure becomes silent | Operational data loss | Bounded retry backoff, attempts, `last_error`, lease release, reporting of exception | Outbox failure/retry test |
| Tenant context omitted from cache/job/storage/export/log key | Cross-tenant collision or disclosure | Immutable serializable `TenantContextSnapshot`; tenant-prefixed cache/storage/export helpers; request middleware attaches and clears snapshot | Snapshot unit tests and request-context test |
| Path traversal in tenant storage/export paths | File boundary escape | Tenant path helper normalizes/rejects unsafe segments rather than accepting arbitrary relative traversal | Tenant snapshot traversal tests |
| Audit event loses actor/tenant/correlation | Weak incident attribution | Shared `AuditRecorder`; actor, alliance, subject, request ID, and trace ID where available; replacement invitation revocations are explicitly auditable | HTTP creation, invitation replacement, and privileged-action audit assertions |

## Abuse and rate-limit boundaries

- Login is throttled by normalized email plus source IP.
- Registration is throttled by normalized email plus source IP.
- MFA challenge is separately throttled.
- Verification and password-recovery routes use explicit throttles.
- Invitation mutation requires authenticated, verified, recently password-confirmed, tenant-authorized leadership access.

## Cryptographic handling

- Passwords use Laravel's configured password hashing and strong validation rules.
- MFA secrets use encrypted model casts and are excluded from serialization.
- MFA recovery codes are stored as SHA-256 hashes inside the encrypted recovery-code value and displayed only at creation/regeneration.
- Invitation bearer tokens contain 256 bits of random entropy and are never stored in plaintext; only SHA-256 token hashes are persisted.
- Hosted transport/session requirements remain governed by `docs/SECURITY_BASELINE.md`.

## Authorization model decision

Phase 1 delivers fixed, system-defined alliance role templates and their permission grants. It exposes audited membership role assignment/removal, not arbitrary editing of permission definitions or role templates. `alliance.manage` and later-domain permissions are provisioned now so later phases can authorize their operations without changing the identity model.

If custom role or permission-template editing is introduced later, it must add explicit owner-level authorization, transactional permission-diff persistence, before/after audit metadata, transactional outbox events, self-lockout protection, and cross-alliance privilege-escalation tests.

## Residual risks and deferred work

- Phase 1 provides the tenant-context primitive for queued jobs, notifications, exports, storage, and cache; domain-specific asynchronous workloads arrive in later phases and must consume this primitive rather than invent their own tenant keying.
- Platform-administrator MFA enforcement is deferred to Phase 6 because no platform-admin surface exists in Phase 1.
- A dedicated external identity provider / WebAuthn implementation is not in Phase 1 scope; current MFA is TOTP plus recovery codes.
- Alliance profile/settings editing is introduced with later alliance-content/public-presence work; Phase 1 creates and authorizes the `alliance.manage` permission but exposes no unaudited settings mutation surface.
- Formal browser-based accessibility and penetration testing should be repeated before production launch; Phase 1 exit uses automated application/security gates plus targeted static security/accessibility review.

## Exit assessment

No unresolved critical or high-risk Phase 1 threat remains in the implemented scope. Any later domain that introduces new alliance-owned rows, queues, notifications, exports, storage, mutable role-permission templates, or platform administration must preserve the explicit tenant boundary and extend this threat model.
