# Platform security profile

[← Platform domain](../README.md)

**Document type:** Living domain security profile  
**Status:** Current  
**Owning domain:** Platform  
**Code owner:** `app/Domain/Platform`  
**Primary security boundary:** separately granted, verified and MFA-backed cross-tenant administration with recent password confirmation for privileged actions

## 1. Security purpose and scope

Platform protects the application's cross-tenant administrative plane and shared infrastructure controls. It covers Platform administrator grants, plans/settings/flags, lifecycle/legal-hold/deletion/retention/export orchestration, and transactional outbox infrastructure.

The two independently high-risk capabilities are reviewed in [Lifecycle and retention security review](lifecycle-and-retention-security-review.md) and [Transactional outbox security review](transactional-outbox-security-review.md).

## 2. Assets and sensitive data

Assets include Platform-administrator grants, tenant fleet/lifecycle state, plans/entitlements/settings/feature flags, legal holds, deletion/restoration state, usage/export metadata, and generic outbox message/attempt/error state.

Cross-tenant administration can expose metadata from many Alliances and is more privileged than ordinary Alliance management. Export/outbox payloads can contain tenant-private identifiers/data and require minimization.

## 3. Actors, authentication and authorization

Platform web administration requires an active Platform grant plus verified Identity, confirmed MFA/session assurance, and recent password confirmation on privileged routes. Alliance ownership/roles or active tenant selection do not confer Platform authority.

The supported workflow prevents a Platform administrator from revoking their own grant and production readiness requires sufficient active administrators according to the shared baseline.

## 4. Tenant and privacy boundaries

Platform intentionally crosses tenant boundaries only through dedicated Platform authority. It does not activate `AllianceContext` as a shortcut for cross-tenant permission.

When Platform orchestrates feature-domain lifecycle/export work, each feature domain retains semantic ownership and its privacy/history constraints. Cross-tenant views/exports must be explicit, privileged, attributable, and bounded.

## 5. Trust boundaries and data flows

Material boundaries include privileged Platform browser → cross-tenant administration, Platform orchestration → Alliance/feature-domain lifecycle contracts, scheduler/maintenance workers → retention/usage/outbox state, producer transaction → shared outbox persistence, and outbox publisher → internal consumers/Integrations fan-out.

Horizon visibility is part of the privileged operational plane; generic member roles do not grant it.

## 6. Threats, abuse cases and controls

Threats include Alliance-role escalation into Platform access, stolen privileged session, self-revocation/admin lockout, destructive deletion despite legal hold, cross-tenant export abuse, lifecycle cleanup bypassing domain rules, sensitive outbox payloads, duplicate publication, retry storms, and treating internal events as automatically public.

Controls include dedicated grants, verified/MFA/password assurance, self-revocation guard, legal-hold/lifecycle state machines, domain-owned orchestration contracts, attributable privileged actions, minimized outbox payloads, at-least-once idempotency semantics, bounded retries, and Integrations-owned external eligibility.

## 7. Integrity, concurrency and idempotency

Destructive lifecycle transitions use explicit persisted state, guards, and transactions rather than ad hoc deletes. Legal holds block destructive work. Restoration/deletion/export operations are designed to be attributable and repeat-safe according to their capability contracts.

Outbox publication uses stable message identity, row claiming/leases, attempts and bounded backoff so concurrent publishers do not intentionally double-claim one row; consumers still tolerate at-least-once delivery.

## 8. Secrets and credential handling

Platform owns no user password/MFA secret and does not expose managed-secret values through the console. Runtime application/database/Redis/object-storage/third-party secrets belong in approved secret-management configuration and must not appear in Platform settings, feature flags, logs, audit metadata, outbox payloads, exports, or documentation.

Platform grants are authorization state, not bearer secrets.

## 9. Destructive operations, retention and deletion

Platform is the primary orchestration owner for legal hold, tenant deletion/restoration, account anonymization coordination, retention, and approved exports. These are security/privacy state machines, not cleanup utilities.

Feature domains define what data/history may be removed, anonymized, or retained; Platform coordinates without rewriting domain semantics. Details are in the focused lifecycle review.

## 10. Auditability, observability and evidence

Platform-admin grant/lifecycle/export/settings/flag actions are attributable. Operators distinguish grant/Identity assurance, lifecycle/hold state, domain-specific failure, outbox queue state, and production infrastructure evidence.

Tests cover Platform-admin assurance, lifecycle/legal holds, export/retention, outbox semantics, domain ownership, and Horizon authorization. Shared production evidence limits are in [Security baseline](../../../security/security-baseline.md).

## 11. Residual risks and explicit non-capabilities

Repository controls cannot prove actual production administrator identities/on-call coverage, managed-secret ownership, ingress/firewall/egress, alert routing, or complete media/key recovery. Those remain production evidence.

Platform does not support support-user impersonation, payment processing, Alliance-role-derived Platform access, ownership of Integrations credentials, or automatic production approval from green CI.

## 12. Focused reviews and related documentation

- [Lifecycle and retention security review](lifecycle-and-retention-security-review.md)
- [Transactional outbox security review](transactional-outbox-security-review.md)
- [Alliance lifecycle and retention contract](../lifecycle-and-retention.md)
- [Transactional outbox contract](../transactional-outbox.md)
- [Identity security profile](../../identity/security/README.md)
- [Audit security profile](../../audit/security/README.md)
- [Security baseline](../../../security/security-baseline.md)
- [Phase 6 threat model](../../../security/phase-6-threat-model.md)
