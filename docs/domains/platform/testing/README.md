# Platform testing and evidence

[← Platform domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Platform  
**Code owner:** `app/Domain/Platform`  
**Primary validation boundary:** Platform-admin separation, tenant lifecycle/legal holds/export, transactional outbox, runtime launch controls, capacity signals, and recovery gates  
**P5 evidence decision:** Living suite map with P5-hardened Phase 6 and production-hardening evidence reused

## 1. Critical claims and validation ownership

Platform validation must prove strict separation of Platform administration from Alliance roles, high-assurance `/platform`/Horizon access, tenant lifecycle/ownership/legal-hold/account-deletion safety, plan/quota/config/feature behavior, privileged Alliance export, transactional-outbox durability/publication, runtime configuration/launch checks and repository-controlled staging/recovery gates.

## 2. Executable suite mapping

All six PHPUnit evidence classes are material: `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`.

Architecture protects authority/domain separation; Feature protects Platform web/admin workflows; Integration protects lifecycle/outbox/configuration/cross-domain orchestration; Performance protects explicit bounded capacity/usage/integration claims; TenantIsolation protects cross-tenant administrative targeting/disclosure; Unit protects deterministic validators/state logic.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Platform-administrator grants as separate from Alliance Authorization, Platform ownership of outbox infrastructure and lifecycle orchestration, Integrations ownership of API/webhook delivery, and feature-domain ownership of business semantics.

It also protects production-approval separation: repository CI/hardening may be accepted while real production cutover remains separately unapproved.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers verified/MFA/password-confirmed Platform access, administrator grant/revoke, target-Alliance resolution without ordinary active-tenant authority, legal holds, deletion/restore/ownership blockers, export authorization and no support impersonation.

[Platform security](../security/README.md) defines the high-assurance/destructive/privacy boundary.

## 5. Feature, interface and integration validation

Feature evidence covers `/platform`, `/health/ready`, administrator/lifecycle/plan/settings/features/usage/export/legal-hold workflows. Integration evidence covers account-deletion orchestration, feature-domain coordination, API/webhook entitlements and outbox consumers.

[Platform interfaces](../interfaces/README.md) maps web/CLI/readiness/outbox surfaces.

## 6. Idempotency, concurrency and asynchronous validation

Transactional outbox recording/publication uses durable idempotency/claim/retry state and at-least-once `OutboxPublished` delivery; downstream retries must not replay originating business mutations.

Lifecycle/account-deletion/retention work rechecks persisted blockers/deadlines/legal holds. Queue/integration recovery uses bounded supported actions rather than direct status fabrication.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 6 exit report](../../../product/phase-6-exit-report.md) records Phase 6 migration/rollback/reapply, staging and recovery evidence and was P5-hardened with exact implementation/final workflow IDs.

Current CI demonstrates clean PostgreSQL migration, immutable image build, ephemeral staging, backup/restore and scan. [Platform operations](../operations/README.md) and lifecycle/outbox runbooks distinguish database restore, application rollback and external-side-effect reconciliation.

## 8. Performance, query and capacity evidence

`Performance` evidence applies to explicit bounded Platform/integration/fleet usage and realistic capacity claims. Plan quotas, API/webhook counts, queue partitioning and synchronous export safety bounds are executable contract constraints.

No global production latency/SLO is inferred from repository tests; production capacity/alert ownership remains external where [production launch approval](../../../product/production-launch-approval.md) says evidence is pending.

## 9. Accessibility and frontend evidence

[Phase 6 accessibility review](../../../product/phase-6-accessibility.md) and source guards cover Platform administrator surfaces. `npm run check` protects frontend lint/format/type/build but is not production accessibility certification.

## 10. Historical accepted evidence

Primary evidence is [Phase 6 exit report](../../../product/phase-6-exit-report.md): implementation `d1969889ffa044cd7690f263ba9ef70c63a425cb` with DR `31235514849`, CodeQL `31235514858`, CI `31235514843`; final PR head `35979623d8231ee56b8fbcb75301e7e0732df0ca` with DR `31252682835`, CodeQL `31252682836`, CI `31252682853`.

[Production hardening exit report](../../../product/production-hardening-exit-report.md) provides later repository-controlled launch-check/release evidence while preserving the separate real-production approval boundary.

## 11. Evidence identity, retention and supersession

Phase 6/hardening SHAs and workflow IDs remain historical. Current Platform validation follows current code/tests and living contracts.

Future Platform acceptance must record exact validated/final revisions and protected runs under [testing/evidence standard](../../../product/testing-evidence-standard.md), without converting repository CI into external production evidence.

## 12. Gaps, non-capabilities and related documentation

No support impersonation, payment processing, automatic real-production approval, generic externalization of every outbox event, or Alliance-role-derived Platform authority is accepted. Production infrastructure/capacity/operational-owner evidence remains separate where not recorded in the repository.

Related documentation:

- [Platform domain](../README.md)
- [Platform security](../security/README.md)
- [Platform operations](../operations/README.md)
- [Platform interfaces](../interfaces/README.md)
- [Transactional outbox](../transactional-outbox.md)
- [Lifecycle and retention](../lifecycle-and-retention.md)
- [Integrations testing](../../integrations/testing/README.md)
- [Identity testing](../../identity/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
