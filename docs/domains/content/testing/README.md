# Content testing and evidence

[← Content domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Content  
**Code owner:** `app/Domain/Content`  
**Primary validation boundary:** Public/member/manager disclosure, revision/publication state, private media safety, and scheduled publication  
**P5 evidence decision:** Living suite map with Phase 2 accessibility/migration/security evidence reused

## 1. Critical claims and validation ownership

Content validation must prove public/member/manager visibility separation, draft/revision/publication invariants, tenant-scoped category/media references, scheduled publication safety, and private screened media/public-branding eligibility.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `TenantIsolation`, and `Unit`. Performance evidence is applicable only through broader bounded-work gates; no standalone Content query budget is accepted.

## 3. Architecture and domain-boundary validation

Architecture coverage protects Content ownership, safe frontend rendering constraints, canonical media/public-profile boundaries and the separation from Recruitment's authoritative application availability.

Current P1–P5 documentation architecture tests protect Content domain/security/operations/interfaces/testing discoverability.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation tests cover public non-disclosure of drafts/member-only/revisions, active-Alliance member reads, `content.manage` mutations, password confirmation, cross-Alliance category/media denial and public-branding usability.

[Content security](../security/README.md) and [Content media](../media.md) define the private storage/scanner/public-presentation controls that executable evidence must preserve.

## 5. Feature, interface and integration validation

Feature tests cover public Alliance/content/branding presentation, member content, manager authoring/revisions/publication/archive and media workflows. Integration evidence exercises storage/scanner/publication/outbox collaboration.

[Content interfaces](../interfaces/README.md) remains the current interface inventory.

## 6. Idempotency, concurrency and asynchronous validation

Scheduled publication rechecks due state under locking/idempotency controls. Editing/restoring historical content creates draft revisions rather than silently mutating live publication. Outbox retry never substitutes for replaying the Content mutation.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 2 exit report](../../../product/phase-2-exit-report.md) records the additive Content schema, `ContentMigrationRollbackTest`, staging, PostgreSQL backup/restore and the explicit distinction that database backup does not include private media binaries.

Current recovery-set behavior remains in [Content operations](../operations/README.md) and the scheduled/media runbook.

## 8. Performance, query and capacity evidence

Content has no accepted standalone numeric query SLA. Upload size/storage quota and scheduled publication limit behavior are feature/operations constraints rather than an invented performance score.

## 9. Accessibility and frontend evidence

[Phase 2 accessibility review](../../../product/phase-2-accessibility.md) plus source-level Content accessibility guards cover main landmarks, no raw `v-html`, no positive `tabindex`, explicit button types, labeled controls and semantic first-party/public flows.

`npm run check` remains the current frontend quality gate but does not replace deployment-specific branding contrast/device/assistive-technology checks identified by the accepted review.

## 10. Historical accepted evidence

Primary evidence is [Phase 2 exit report](../../../product/phase-2-exit-report.md), including exact implementation and acceptance heads plus protected run identities.

P2/P3/P4 DCP records later normalize current Content security/operations/interface documentation without changing the historical Phase 2 decision.

## 11. Evidence identity, retention and supersession

Phase 2 test counts, SHAs and workflow IDs remain historical. Current Content validation follows current code/tests and this living profile.

New Content acceptance evidence must record exact revision/workflow identity per [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

No public write API, arbitrary trusted HTML, database-only claim of media recovery, or scanner bypass is accepted. No dedicated automated browser accessibility suite is claimed beyond implemented source guards and protected frontend checks.

Related documentation:

- [Content domain](../README.md)
- [Content media](../media.md)
- [Content security](../security/README.md)
- [Content operations](../operations/README.md)
- [Content interfaces](../interfaces/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
