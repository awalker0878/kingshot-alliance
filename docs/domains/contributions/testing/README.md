# Contributions testing and evidence

[← Contributions domain](../README.md)

**Document type:** Living domain testing and evidence profile  
**Status:** Current  
**Owning domain:** Contributions  
**Code owner:** `app/Domain/Contributions`  
**Primary validation boundary:** Immutable contribution history, Events reconciliation, explainable calculations/reporting, privileged exports, and scheduled-report source state  
**P5 evidence decision:** Living suite map with hardened Phase 5 acceptance/accessibility/migration evidence reused

## 1. Critical claims and validation ownership

Contributions validation must prove approval/correction/reversal history, event-attendance reconciliation, calculation provenance/explainability, tenant-safe member/manager views, data-quality workflow, `phase5.v1` export compatibility, and deterministic scheduled-report source semantics.

## 2. Executable suite mapping

Primary evidence classes are `Architecture`, `Feature`, `Integration`, `Performance`, `TenantIsolation`, and `Unit`. Performance is applicable to bounded reporting/query behavior where executable thresholds exist; no generic throughput SLA is inferred.

## 3. Architecture and domain-boundary validation

Architecture evidence protects Contributions ownership of records/reporting while Events remains attendance source truth and Notifications remains delivery coordinator. It also protects the separate external Integrations projection and the P4 report-export contract.

## 4. Authorization, tenancy, security and privacy validation

Feature/Integration/TenantIsolation evidence covers `contributions.manage`, recent password confirmation, active-Alliance re-resolution, member self-report restrictions, privileged exports, correction/reversal authorization and cross-Alliance denial.

[Contributions security](../security/README.md) defines private/evidence/data-class boundaries that tests must preserve.

## 5. Feature, interface and integration validation

Feature tests cover member progress/history and manager workflows. Integration evidence covers Events reconciliation, Notifications scheduling handoff, Audit/outbox behavior and export-run persistence.

[Report exports](../interfaces/report-exports.md) defines exact CSV/SpreadsheetML/version/checksum semantics; [Contributions interfaces](../interfaces/README.md) maps all boundary families.

## 6. Idempotency, concurrency and asynchronous validation

Correction appends a replacement and reverses the original rather than overwriting history. Events reconciliation is idempotent and can create/reverse/restore derived records as attendance truth changes. Scheduled report requests use deterministic identities through Notifications/outbox.

## 7. Persistence, migration, rollback and recovery evidence

[Phase 5 exit report](../../../product/phase-5-exit-report.md) records migration rollback/reapply and protected recovery evidence. During DCP-P5 that record was traceability-hardened with recovered exact final head and workflow run IDs without changing its original acceptance.

Current PostgreSQL forward migration and backup/restore remain part of CI; domain recovery semantics are in [Contributions operations](../operations/README.md).

## 8. Performance, query and capacity evidence

`Performance` evidence applies to bounded reporting/query regressions where present. The current accepted contract does not define a universal Contributions request-latency SLA.

Export row generation, schedule batch limits and cross-domain reporting views must remain bounded by their executable/operations constraints rather than undocumented assumptions.

## 9. Accessibility and frontend evidence

[Phase 5 accessibility review](../../../product/phase-5-accessibility.md) and structural guards cover the Phase 5 Vue surfaces. `npm run check` protects frontend syntax/style/types/build but is not itself accessibility conformance.

## 10. Historical accepted evidence

Primary historical evidence is [Phase 5 exit report](../../../product/phase-5-exit-report.md), now including final PR head `c30aaab0ee3b03c65f27042a2700540bdebbf9c4` and recovered protected runs DR `31219686800`, CodeQL `31219686802`, CI `31219686960`.

[Event reconciliation](../event-reconciliation.md) and current P2–P4 DCP profiles remain living contracts, not replacements for that historical record.

## 11. Evidence identity, retention and supersession

Historical Phase 5 counts/SHAs/run IDs stay unchanged after recovery. Living testing maps evolve with current code/tests.

Future Contributions acceptance must preserve exact validated SHA/check identities under [testing/evidence standard](../../../product/testing-evidence-standard.md).

## 12. Gaps, non-capabilities and related documentation

No public write API, anonymous export, OOXML `.xlsx` claim, manual correction of Events-derived records, or opaque punitive score is accepted. There is no invented domain SLA beyond executable bounded-work evidence.

Related documentation:

- [Contributions domain](../README.md)
- [Event reconciliation](../event-reconciliation.md)
- [Contributions security](../security/README.md)
- [Contributions operations](../operations/README.md)
- [Contributions interfaces](../interfaces/README.md)
- [P5 evidence matrix](../../../product/testing-evidence-coverage-matrix.md)
