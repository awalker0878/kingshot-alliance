# Documentation maintenance and final-acceptance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P7` — Maintenance automation and final acceptance  
**Inventory state:** Candidate — 100% required content implemented; protected validation pending  
**Content candidate SHA:** `4c3091f8ae92ee450ff3a9ee23df65ab4f193636`

## 1. Purpose

This is the authoritative final DCP inventory. P7 proves P1–P6 completeness remains protected by stable change-driven maintenance rules and deterministic CI rather than creating another domain-content layer.

Governing rules: [Documentation maintenance standard](documentation-maintenance-standard.md). Final evidence: [DCP final exit report](documentation-completion-program-exit-report.md).

## 2. Required P7 artifacts

| Artifact/work item | Status |
| --- | --- |
| `documentation-maintenance-standard.md` | Complete |
| this coverage matrix | Complete |
| `documentation-program-plan.md` standards catalog/final phase model | Complete |
| `documentation-standard.md` maintenance integration | Complete |
| `definition-of-done.md` change-time maintenance integration | Complete |
| `docs/README.md` maintenance navigation | Complete |
| `docs/product/README.md` P7/final evidence navigation | Complete |
| `documentation-program-status.md` P6 final identity + P7 control | Complete |
| `documentation-completion-program-exit-report.md` | Complete; protected validation pending |
| `tests/Architecture/DocumentationMaintenanceTest.php` | Complete |
| complete P0–P7 inventory review | Complete |

## 3. Stable automation result

The final gate plus existing P1–P6 architecture suites protect:

- exactly five top-level documentation groups;
- 14/14 code-domain/docs-domain parity;
- code-local README + canonical domain contract per domain;
- P1–P5 security/operations/interfaces/testing profile parity;
- code-local canonical documentation links;
- local Markdown link integrity;
- no flat domain living Markdown except the domains index;
- filename/path/evidence-placement rules;
- ADR lifecycle/index integrity;
- P6 dependency/glossary/current-audit navigation;
- all specialized P1–P7 standards indexed by canonical filename;
- all P1–P7 standard/coverage/exit governance discoverable;
- maintenance/Definition-of-Done navigation agreement; and
- DCP completion separated from real-production approval.

## 4. Standards catalog result

| Phase | Canonical standard | Current/indexed |
| --- | --- | --- |
| P1 | `domain-contract-standard.md` | Yes |
| P2 | `security-documentation-standard.md` | Yes |
| P3 | `operations-documentation-standard.md` | Yes |
| P4 | `interface-documentation-standard.md` | Yes |
| P5 | `testing-evidence-standard.md` | Yes |
| P6 | `architecture-governance-standard.md` | Yes |
| P7 | `documentation-maintenance-standard.md` | Yes |

P7 corrected the stale P5 program-plan filename `testing-evidence-documentation-standard.md` to `testing-evidence-standard.md`.

## 5. Change/evidence/archival result

The maintenance standard now covers all material domain, security/privacy, operations, interface/integration, validation/evidence, architecture, product/status, shared runtime/security/production, and documentation-structure changes.

It preserves living-current versus historical evidence semantics, distinct DCP/ADR/product status vocabularies, Accepted versus Approved, and repository hardening versus real-production approval.

Stale material is classified as Current, Historical evidence, Superseded, or Obsolete duplicate. Current documents are reviewed when material changes affect them rather than through forced calendar rewrites.

## 6. Non-brittle automation boundary

Final automation does **not** parse every method/class/import, infer ownership from raw dependency counts, require prose churn for harmless refactors, compare historical evidence to current test totals, or require one document per route/controller/test/class.

## 7. Prior-phase preservation review

The final inventory retains P1–P7 standards/coverage/exit governance, all 14 domain roots/P1–P5 profiles, ADR/current architecture, dependency map, glossary, current capability/audits, shared security/operations, and production launch approval as a distinct not-yet-approved decision.

## 8. P7 exit checklist

- [x] Maintenance standard adopted.
- [x] Final maintenance/final-acceptance inventory frozen.
- [x] Program plan standards catalog corrected.
- [x] Repository documentation standard linked to maintenance.
- [x] Definition of Done linked to change-time obligations.
- [x] Repository/product navigation updated.
- [x] P6 final protected identity recorded.
- [x] Final maintenance architecture test active.
- [x] Complete P0–P7 inventory review complete.
- [x] Final DCP exit report created with content candidate `4c3091f8ae92ee450ff3a9ee23df65ab4f193636`.
- [ ] Exact P7 candidate/evidence head protected-green.
- [ ] Exact final DCP evidence/status head protected-green.

P7 is **Candidate**. There is no P8. After both protected gates close, future documentation work is normal change-driven maintenance.
