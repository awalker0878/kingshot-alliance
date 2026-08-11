# Platform domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/Platform`  
**Primary authorization boundary:** verified/MFA-backed Platform administrator grant with recent password confirmation

## 1. Purpose and ownership

Platform owns cross-tenant administration and shared platform controls: Platform administrator grants, Alliance lifecycle/data-governance orchestration, plans/entitlements/settings/feature flags, usage/export control state, and shared transactional-outbox infrastructure.

Platform administration is deliberately separate from Alliance RBAC and does not gain authority by switching tenant context.

## 2. Scope

In scope: Platform-admin authorization, lifecycle/provisioning/ownership transfer, plans/entitlements/settings/flags, legal holds/deletion/retention, usage/export orchestration, and shared outbox infrastructure.

Out of scope: Alliance role authorization, support impersonation, payment processing, feature-domain semantic ownership, Integrations credential/webhook ownership, and automatic production approval from repository CI.

## 3. Domain model

Platform administrator grants are cross-tenant eligibility records combined with Identity assurance.

Two independently material capabilities are documented separately:

- [Alliance lifecycle and retention](lifecycle-and-retention.md) — destructive/cross-tenant lifecycle, holds, deletion/restoration, retention/export.
- [Transactional outbox](transactional-outbox.md) — generic durable asynchronous event infrastructure used by feature domains.

Plans/entitlements/settings/feature flags remain coherent Platform configuration in this root.

## 4. Core invariants

1. Platform-admin authority is separate from Alliance roles/context.
2. Web administration requires the Platform grant plus verified Identity, MFA/session, and recent password confirmation.
3. Feature domains retain semantic ownership of their business state.
4. Platform plan/entitlement data does not move enforcement ownership out of the feature domain.
5. Feature flags are explicit product controls rather than hidden compatibility behavior.
6. Destructive lifecycle/retention rules follow [lifecycle-and-retention.md](lifecycle-and-retention.md).
7. Shared durable event behavior follows [transactional-outbox.md](transactional-outbox.md).

## 5. Lifecycles and workflows

Platform administrators may grant/revoke Platform access through the supported bootstrap/admin workflow, assign plans/settings/flags, inspect fleet/usage state, and execute approved lifecycle/export actions.

Alliance lifecycle/legal hold/deletion/restoration/export behavior is defined in [Alliance lifecycle and retention](lifecycle-and-retention.md). Generic outbox record/publish/recovery is defined in [Transactional outbox](transactional-outbox.md).

## 6. Authorization and tenancy

Cross-tenant Platform routes use a dedicated administrator grant plus Identity assurance and do not activate `AllianceContext` to obtain authority. Feature-domain operations invoked by Platform orchestration still respect the owning domain's safety contracts.

## 7. Cross-domain contracts

Consumes Identity assurance/account lifecycle, Memberships/Authorization same-Alliance target validation where required, Audit evidence, and feature-domain counts/state needed by approved orchestration.

Exposes Alliance lifecycle state, plans/entitlements/settings/flags, Platform-admin surfaces, and the [transactional outbox](transactional-outbox.md).

## 8. Persistence and data ownership

Platform owns administrator grants, plans/entitlements/assignments, Platform settings/flags, legal holds, usage/export metadata, lifecycle orchestration state, and outbox infrastructure. Feature domains retain their business persistence ownership.

## 9. Events, outbox and integrations

Platform owns generic outbox infrastructure; producer domains own event meaning and Integrations owns external webhook delivery. See [Transactional outbox](transactional-outbox.md).

## 10. HTTP, UI and API surfaces

Platform console/Horizon access are cross-tenant administrative surfaces protected by the Platform/Identity boundary. Alliance first-party/API surfaces remain separate tenant-scoped contracts.

## 11. Background processing

Platform coordinates outbox publication, operational retention, usage snapshots, and maintenance through the shared scheduler/queue model. Queue/runtime details remain shared Operations documentation.

## 12. Failure, idempotency and concurrency

Sensitive lifecycle rules are defined in [lifecycle-and-retention.md](lifecycle-and-retention.md); durable event retry/at-least-once behavior is defined in [transactional-outbox.md](transactional-outbox.md). Platform-admin self-revocation remains rejected by the supported admin workflow.

## 13. Security and privacy

Cross-tenant Platform access is high privilege. Exports/redaction/legal holds/deletion/anonymization and outbox payload minimization are security/privacy controls, not optional cleanup behavior.

## 14. Observability and operations

Platform provides fleet/usage/queue visibility while shared Operations documents scheduler, queues, observability, deployment, recovery, and production evidence boundaries.

## 15. Testing and architecture enforcement

Tests protect Platform-admin assurance, lifecycle/legal-hold behavior, plans/entitlement integration, export/retention controls, outbox semantics, and feature-domain ownership boundaries.

## 16. Explicit non-capabilities

Platform does not implement support impersonation, payment processing, Alliance-role-based Platform administration, Integrations credential/webhook ownership, or automatic real-production approval.

## 17. Capability documents

- [Alliance lifecycle and retention](lifecycle-and-retention.md)
- [Transactional outbox](transactional-outbox.md)

## 18. Related documentation

- [Identity](../identity/README.md)
- [Memberships](../memberships/README.md)
- [Authorization](../authorization/README.md)
- [Integrations](../integrations/README.md)
- [Audit](../audit/README.md)
- [Background processing](../../operations/background-processing.md)
- [Security baseline](../../security/security-baseline.md)
- [`app/Domain/Platform/README.md`](../../../app/Domain/Platform/README.md)
