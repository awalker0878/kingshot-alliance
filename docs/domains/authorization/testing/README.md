# Authorization testing and evidence

[← Authorization domain](../README.md)

**Document type:** Living domain testing and evidence profile
**Status:** Current
**Owning domain:** Authorization
**Code owner:** `app/Domain/Authorization`
**Primary validation boundary:** Alliance and exact-Kingdom permission evaluation, rank hierarchy/assignment safety, Player identity binding, and separation from Identity/Platform authority
**P5 evidence decision:** Living suite map with Phase 1/6 historical evidence reused

## 1. Critical claims and validation ownership

Authorization validation must prove stable permission vocabulary, Alliance scoping, exact-Kingdom role scoping, Player current-roster identity binding, hierarchy-aware mutation, active-R5 leadership protection, final-Kingdom-Admin safety, and strict separation from Platform-administrator grants.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. No dedicated Authorization `Performance` threshold is accepted.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Authorization ownership of roles/permissions and prevents feature domains or Membership route adapters from inventing independent permission semantics.

It also protects Platform administrator authority as a different domain contract rather than another Alliance role.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers permission denial, cross-Alliance role/membership substitution, cross-Kingdom role substitution, Player roster/membership mismatch, effective hierarchy, active-membership requirements, R5 leadership safety, final-Kingdom-Admin safety, and privileged mutation boundaries.

[Authorization security](../security/README.md) defines the fail-closed boundary that the test evidence must preserve.

## 5. Feature, interface and integration validation

Memberships-mediated role routes are validated as adapters into Authorization-owned actions. Feature domains consume stable permission keys through supported evaluators and policies.

[Authorization interfaces](../interfaces/README.md) remains authoritative for the supported internal contract and caller boundaries.

## 6. Idempotency, concurrency and asynchronous validation

Duplicate role assignment is a no-op; remove/reassign cycles can produce legitimate new transitions without outbox identity collisions. R5 leadership and Kingdom-role mutations are transactionally guarded.

These repeat/concurrency semantics were specifically hardened in Phase 1 and remain regression-sensitive.

## 7. Persistence, migration, rollback and recovery evidence

Phase 1 acceptance covers role/permission persistence and composite same-Alliance constraints. EVENTS-002 adds a forward rank/permission conversion migration and composite Kingdom role-assignment constraints; rollback/reapply tests protect both boundaries.

Recovery behavior is documented in [Authorization operations](../operations/README.md); direct row edits that bypass hierarchy/tenant constraints are not an accepted test or recovery path.

## 8. Performance, query and capacity evidence

Authorization has no accepted standalone query-budget/SLA. Permission/hierarchy lookups are exercised inside first-party workflows and broader realistic-volume tests. Do not infer a numeric threshold from current implementation shape.

## 9. Accessibility and frontend evidence

Alliance role/membership administration formed part of the accepted [Phase 1 accessibility review](../../../product/phase-1-accessibility-review.md). Current frontend quality uses `npm run check`; semantic accessibility evidence remains in the accepted review/source guards rather than this backend authorization profile.

## 10. Historical accepted evidence

Primary evidence is [Phase 1 exit report](../../../product/phase-1-exit-report.md). [Phase 6 exit report](../../../product/phase-6-exit-report.md) additionally proves Platform-administrator separation from Alliance roles.

## 11. Evidence identity, retention and supersession

Phase 1/6 SHA/run identities remain immutable historical evidence. Living permission vocabulary/test mapping changes only with current code/tests and must not rewrite historical counts.

New acceptance evidence follows [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

Authorization has no public RBAC API, arbitrary hidden permission vocabulary, or Platform-admin derivation from Alliance roles. Absence of those surfaces is an enforced architecture/security boundary, not a missing feature test.

Related documentation:

- [Authorization domain](../README.md)
- [Authorization security](../security/README.md)
- [Authorization operations](../operations/README.md)
- [Authorization interfaces](../interfaces/README.md)
- [Memberships testing](../../memberships/testing/README.md)
- [Platform testing](../../platform/testing/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
