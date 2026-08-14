# Alliances testing and evidence

[← Alliances domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Alliances  
**Code owner:** `app/Domain/Alliances`  
**Primary validation boundary:** Active-Alliance tenant context, Alliance lifecycle/activation, and tenant-safe collaboration with feature domains  
**P5 evidence decision:** Living suite map with Phase 1 historical evidence reused

## 1. Critical claims and validation ownership

Alliances testing must prove that active tenant context is explicit and fail-closed, that Alliance creation/activation cannot fabricate access, and that feature domains receive the same resolved tenant identity rather than trusting caller-selected IDs.

Material claims include active Player membership as a prerequisite to active context, stale/suspended membership invalidating context, tenant snapshot consistency, and Alliance Kingdom being derived rather than independently mutable.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, and `TenantIsolation` from `phpunit.xml`. `Unit` applies to deterministic context/value behavior where isolated tests exist; no separate Alliance performance threshold is claimed.

Repository acceptance uses full `composer check`, not one targeted Alliance test.

## 3. Architecture and domain-boundary validation

`tests/Architecture` protects domain ownership/dependency direction and the canonical domain/documentation structure. P1–P5 documentation architecture guards make the Alliance domain, security, operations, interfaces, and testing profiles discoverable.

Architecture evidence also protects the separation among Alliances tenant context, Memberships relationship state, Authorization permission state, and Platform administrator authority.

## 4. Authorization, tenancy, security and privacy validation

`Feature`, `Integration`, and `TenantIsolation` evidence covers active membership/context selection, cross-Alliance denial, stale/suspended context failure, Alliance activation restrictions, and same-tenant feature lookup behavior.

Security expectations remain owned by [Alliances security](../security/README.md). Testing must not treat a neutral Kingdom/game-Alliance reference, session parameter, or arbitrary route identifier as tenant authority.

## 5. Feature, interface and integration validation

First-party Alliance creation/activation/overview behavior is validated at feature/application level. Cross-domain integration evidence proves `AllianceContext` is consumed by tenant-owned domains while Content/Integrations own their separate public/machine representations.

Interface ownership and the internal-only Kingdom event boundary are documented in [Alliances interfaces](../interfaces/README.md).

## 6. Idempotency, concurrency and asynchronous validation

Alliance lifecycle mutations rely on owning transactional actions. Material outbox evidence is tested through the shared Platform integration path; outbox retries must not replay the originating Alliance mutation.

Alliance Kingdom has no standalone mutation event; Integrations regression evidence keeps all `kingdoms.*` event families externally ineligible.

## 7. Persistence, migration, rollback and recovery evidence

Phase 1 acceptance proves the foundational Alliance/membership/authorization schema and tenant-context behavior on PostgreSQL, including protected migrations, staging, backup/restore, and tenant isolation.

Current CI still performs clean forward migrations and backup/restore. Domain-specific recovery semantics are in [Alliances operations](../operations/README.md).

## 8. Performance, query and capacity evidence

Alliances has no standalone accepted query-count SLA. Bounded tenant/context lookups are exercised indirectly through Feature/Integration/TenantIsolation and broader realistic-volume gates. Do not invent an Alliance-specific performance threshold without an executable acceptance criterion.

## 9. Accessibility and frontend evidence

Alliance creation/switching/administration UI was included in the accepted [Phase 1 accessibility review](../../../product/phase-1-accessibility-review.md). Current frontend quality is covered by `npm run check`; this is not a substitute for deployment-specific accessibility validation.

## 10. Historical accepted evidence

Primary historical acceptance is [Phase 1 exit report](../../../product/phase-1-exit-report.md), with validated head `ca3d5ad851ec88a0ef127817a3fbf670f7a0352c` and protected CI/security workflow identity recorded there.

Later phases and Kingdoms increments reuse the accepted tenant-context primitive rather than superseding its ownership.

## 11. Evidence identity, retention and supersession

Current behavior follows current code/tests/living docs. Phase 1 counts/check IDs remain historical and are not updated to current repository totals.

New Alliance acceptance evidence must record exact SHA and protected workflow run IDs under the [testing/evidence standard](../../../product/testing-evidence-standard.md). A later test refactor may supersede the living suite mapping without deleting historical acceptance.

## 12. Gaps, non-capabilities and related documentation

No dedicated Alliance performance SLA, public write API, or alternate tenant-selection mechanism is accepted. Testing must preserve those non-capabilities rather than create evidence for unsupported behavior.

Related documentation:

- [Alliances domain](../README.md)
- [Alliances security](../security/README.md)
- [Alliances operations](../operations/README.md)
- [Alliances interfaces](../interfaces/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
