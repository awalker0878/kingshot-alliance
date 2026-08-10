# Platform domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Platform`  
**Primary authorization boundary:** verified/MFA-backed platform-administrator grant with recent password confirmation

## 1. Purpose and ownership

Platform owns cross-tenant platform administration and the controls needed to operate many Alliances safely. Platform administration is deliberately separate from Alliance administration: it is not an Alliance role and does not reuse Alliance permission rows.

Platform also owns platform plans/entitlements, Alliance platform settings/feature flags, legal holds, retention orchestration, usage/operational snapshots, tenant-complete administrative export, and the shared transactional-outbox infrastructure used by feature domains.

## 2. Scope

### In scope

- platform-administrator grant/access boundary;
- Alliance provisioning and platform lifecycle control;
- ownership transfer as a platform-controlled Alliance lifecycle operation;
- plan/entitlement assignment and Alliance platform settings;
- feature flags;
- usage snapshots and fleet/queue operational visibility;
- legal holds, account deletion orchestration, and operational retention;
- tenant-complete administrative export; and
- shared transactional-outbox infrastructure.

### Out of scope

- Alliance role authorization;
- support impersonation, which is intentionally not implemented;
- payment processing;
- ownership of feature-domain business records;
- API credential/webhook persistence, which belongs to Integrations; and
- treating repository-controlled validation as proof of real production infrastructure controls.

## 3. Domain model

### Platform administrators

An active `platform_administrators` row grants cross-tenant administrative eligibility, but web access additionally requires verified email, confirmed MFA, authenticated session, and recent password confirmation.

### Alliance lifecycle

Platform lifecycle states are:

- `active`;
- `suspended`;
- `closed`; and
- `deleted`.

Alliance tenant context and Alliance API authentication accept only `active` Alliances.

### Plans and entitlements

The payment-independent plan foundation includes:

- `platform_plans`;
- `platform_plan_entitlements`; and
- `alliance_plan_assignments`.

The standard plan defines limits for active/pending members, storage bytes, active API credentials, and active webhook subscriptions.

### Alliance platform settings and feature flags

`alliance_platform_settings` stores retention window, queue partition, API availability, and webhook availability.

`alliance_feature_flags` stores Alliance-local flags and optional JSON configuration.

### Legal holds and deletion

Legal holds can target a User or Alliance and block destructive processing for the subject while active. Account deletion has a seven-day cooling-off period and may be blocked by platform-admin status, active Alliance ownership, or legal hold.

### Usage and exports

Usage snapshots record active members, media storage, API credentials, webhook subscriptions, and unpublished outbox messages. Platform also records export evidence for tenant-complete administrative exports.

## 4. Core invariants

1. Platform-administrator access is cross-tenant and never modeled as an Alliance role.
2. An administrator grant alone is insufficient for web access; verified email, MFA, authenticated session, and recent password confirmation are also required.
3. Platform routes do not activate `AllianceContext` merely to obtain cross-tenant authority.
4. Alliance lifecycle mutations require a reason, run under transaction/row lock, and produce audit/outbox evidence.
5. Alliance tenant context and Alliance API authentication accept only `active` Alliances.
6. Logical deletion requires a closed Alliance and is blocked by an active Alliance legal hold.
7. Deleted-Alliance restoration is allowed only before the retention deadline.
8. Plan/entitlement enforcement remains in the owning feature domain even though Platform owns the plan data.
9. Feature flags are explicit product controls, not hidden compatibility behavior.
10. Support impersonation remains absent until separately approved.

## 5. Lifecycles and workflows

### Bootstrap/revoke platform administrator

Bootstrap is explicit through:

```text
php artisan platform:admin:grant user@example.com
```

The command does not disable MFA requirements. Administrator grant/revocation is audited. Self-revocation is rejected so an operator cannot accidentally remove the current session's access path.

### Alliance lifecycle

The platform console may provision, suspend, close, logically delete, restore, export, and transfer ownership.

Closing records a restoration/retention deadline. Logical deletion requires the Alliance to already be closed and fails when a legal hold applies.

### Ownership transfer

The target must be an active membership in the same Alliance. The target receives Owner; previous owners are demoted to Leader. The change is audited and produces an outbox event.

### Plan/entitlement administration

New and existing Alliances receive the standard plan. Feature domains enforce relevant limits: invitation issuance/member capacity in Memberships, storage/media capacity in Content, and API/webhook capacity in Integrations.

### Usage/operational visibility

The platform console surfaces queue sizes, failed-job count, pending/failed webhooks, usage snapshots, and fleet lifecycle counts. Horizon remains the detailed queue/worker operational surface.

### Account deletion

After the cooling-off period and eligibility checks, deletion revokes tokens, ends active memberships, and anonymizes the User while preserving pseudonymized audit/business history.

### Operational retention

Current documented retention behavior includes:

- redact old webhook payload/error bodies after 30 days;
- remove long-revoked API credentials after 90 days; and
- remove old usage/export metadata after one year.

### Alliance export

Platform administrators can generate tenant-complete JSON export by discovering PostgreSQL tables carrying `alliance_id` and exporting rows for the requested Alliance. Known secret/verifier columns are redacted.

Each export records schema version, requester, row count, SHA-256 checksum, generated time, and per-table counts in audit evidence. A **100 MiB** synchronous safety bound prevents one operator request from monopolizing the web worker.

## 6. Authorization and tenancy

Platform administration uses a dedicated cross-tenant administrator grant plus verified email, MFA, authenticated session, and recent password confirmation. It does not reuse `alliance.view`, `alliance.manage`, or Alliance role membership as the platform-admin authority model.

Feature-domain operations initiated from platform workflows must still honor the ownership and data-safety contracts of those domains.

## 7. Cross-domain contracts

### Consumes

- **Identity** — verified User identity, session, MFA, password confirmation, and deletion/anonymization coordination.
- **Memberships/Authorization** — active same-Alliance membership is required for ownership-transfer target validation, without transferring ownership of membership/role persistence to Platform.
- **Audit** — attributable administrative evidence.
- **Feature domains** — usage counts and tenant-owned rows used by export/lifecycle orchestration.

### Exposes

- plan/entitlement/settings/feature-flag controls consumed by owning feature domains;
- shared transactional-outbox recording/publishing infrastructure;
- Alliance lifecycle state consumed by tenant context and API authentication; and
- cross-tenant operator surfaces under the platform-admin boundary.

## 8. Persistence and data ownership

Platform owns platform-administrator grants, plans/entitlements/assignments, Alliance platform settings, feature flags, legal holds, usage snapshots, export metadata, lifecycle orchestration state, and transactional-outbox infrastructure.

Feature domains remain owners of their business persistence. Platform lifecycle/export orchestration does not make Platform the semantic owner of Content, Events, Recruitment, Contributions, Kingdoms, or Integrations records.

## 9. Events, outbox and integrations

Lifecycle/administrator/platform-setting changes are audited and use the transactional outbox when durable events are required.

Platform owns the generic outbox infrastructure, but event producers own their business event semantics. Internal outbox publication does not by itself create an external webhook contract.

Integrations owns API credentials, webhook subscriptions, delivery, signing, and retries. Platform may enforce entitlements/availability without absorbing that persistence.

## 10. HTTP, UI and API surfaces

The platform console and Horizon operational access are cross-tenant administrative surfaces protected by the platform-administrator identity/MFA boundary.

Alliance first-party routes and Alliance API authentication remain separate tenant-scoped surfaces.

## 11. Background processing

Platform coordinates operational retention, usage snapshots, outbox publication, and other platform maintenance through the shared scheduler/queue model.

Queues are partitioned into core/default, notifications, integrations, and maintenance classes so external integration retries cannot consume all application workers.

## 12. Failure, idempotency and concurrency

- Lifecycle mutations use transaction/row locks.
- Destructive processing fails closed under legal hold.
- Alliance deletion requires the closed state first.
- Restoration fails after the retention deadline.
- Ownership transfer validates the active same-Alliance target.
- Tenant export is bounded to 100 MiB synchronously and redacts known secret/verifier columns.
- Platform administrator self-revocation is rejected.

## 13. Security and privacy

Cross-tenant Platform access is a high-privilege boundary requiring verified identity, MFA, and recent password confirmation.

Exports must redact known secrets/verifiers. Legal holds and deletion/anonymization are privacy/data-governance controls and must not be bypassed through direct persistence changes.

Support impersonation remains explicitly absent.

## 14. Observability and operations

Platform exposes usage/fleet/queue visibility while Horizon remains the detailed worker surface. Repository operations guidance covers scheduler/outbox, observability, deployment, recovery, and production evidence boundaries.

See [Background processing](../../operations/background-processing.md), [Observability](../../operations/observability.md), and [Production launch approval](../../product/production-launch-approval.md).

## 15. Testing and architecture enforcement

Tests should protect:

- platform-admin privilege/MFA/password-confirmation boundaries;
- Alliance lifecycle transitions and legal-hold blocking;
- ownership-transfer scope;
- plan/entitlement enforcement integration;
- export redaction/bounds;
- retention/deletion behavior;
- outbox ownership; and
- the architecture rule that Integrations/feature domains retain semantic ownership of their persistence.

## 16. Explicit non-capabilities

Platform does not implement:

- support impersonation;
- payment processing;
- Alliance-role-based platform administration;
- ownership of API credentials/webhooks; or
- automatic production approval based only on repository tests.

## 17. Capability documents

No separate Platform capability files are required at present. Shared operational detail remains under `docs/operations/`.

## 18. Related documentation

- [Identity domain](../identity/README.md)
- [Memberships domain](../memberships/README.md)
- [Authorization domain](../authorization/README.md)
- [Integrations domain](../integrations/README.md)
- [Audit domain](../audit/README.md)
- [Background processing](../../operations/background-processing.md)
- [Observability](../../operations/observability.md)
- [Security baseline](../../security/security-baseline.md)
- [Production launch approval](../../product/production-launch-approval.md)
- [`app/Domain/Platform/README.md`](../../../app/Domain/Platform/README.md)
