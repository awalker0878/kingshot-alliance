# Documentation maintenance and final-acceptance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P7` — Maintenance automation and final acceptance  
**Inventory state:** Frozen — 100% required content implemented; candidate evidence preparation in progress

## 1. Purpose

This is the authoritative final DCP inventory. P7 does not create another domain-content layer; it proves P1–P6 completeness remains protected by stable change-driven maintenance rules and deterministic CI.

The governing rules are in [Documentation maintenance standard](documentation-maintenance-standard.md).

## 2. Required P7 artifacts

| Artifact/work item | Required purpose | Status |
| --- | --- | --- |
| `documentation-maintenance-standard.md` | change-time obligations, evidence/status/navigation/archival/automation lifecycle | Complete |
| this coverage matrix | frozen final maintenance/final-acceptance inventory | Complete |
| `documentation-program-plan.md` | standards catalog uses actual current filenames and final P7 model | Complete |
| `documentation-standard.md` | normal maintenance links to P7 standard | Complete |
| `definition-of-done.md` | change-time maintenance obligations integrated into normal acceptance | Complete |
| `docs/README.md` | maintenance governance exposed from repository docs entry point | Complete |
| `docs/product/README.md` | P7 standard/matrix/final-exit and post-program maintenance indexed | Complete |
| `documentation-program-status.md` | P6 final acceptance identity + current P7 state | Complete |
| final DCP exit report | records P0–P7 completion and exact protected evidence | Candidate evidence preparation |
| final maintenance architecture test | aggregates stable P1–P7 invariants without brittle implementation parsing | Complete |
| complete final inventory review | prior standards/matrices/exits/current navigation present/non-conflicting | Complete |

## 3. Stable automation result

The final maintenance gate plus existing P1–P6 suites protect:

- exactly five top-level documentation groups;
- canonical code-domain/docs-domain parity for all 14 domains;
- code-local README and canonical domain contract per code domain;
- required P1–P5 specialized domain profiles;
- code-local README → canonical documentation linkage;
- local Markdown link integrity;
- no flat living Markdown under `docs/domains/` except `README.md`;
- filename/path rules and accepted exceptions;
- domain-specific evidence placement boundaries;
- ADR index/status lifecycle;
- P6 dependency/glossary/current-audit navigation;
- every specialized DCP standard P1–P7 indexed by its actual filename;
- every prior DCP standard/coverage/exit artifact discoverable;
- current maintenance standard and Definition of Done navigation agreement; and
- final DCP status/exit separation from real-production approval.

Existing P1–P6 tests remain active. `tests/Architecture/DocumentationMaintenanceTest.php` adds only cross-standard/final-program consistency.

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

P7 corrected the stale program-plan P5 filename `testing-evidence-documentation-standard.md` to the actual canonical `testing-evidence-standard.md` and converted the catalog to direct links.

## 5. Change-time obligation result

The maintenance standard covers material changes to:

- domain ownership/model/invariants/lifecycle/contracts;
- security/privacy/tenancy/authentication/authorization/destructive behavior;
- persistent/async/runtime/diagnostic/recovery/rollback/capacity behavior;
- HTTP/UI/API/CLI/event/job/webhook/import/export/media/external integrations;
- validation/evidence/performance/migration/accessibility acceptance;
- durable architecture/ADR/dependency/shared terminology;
- product capability/status/named increments;
- shared runtime/security/production controls; and
- documentation structure/ownership/standards.

Documentation work is impact-driven rather than file-count-driven.

## 6. Evidence and status lifecycle result

Final maintenance preserves:

- living documentation updated with current accepted behavior;
- historical acceptance/decision evidence retained at its recorded identity;
- historical test counts/run IDs kept historical;
- DCP status vocabulary distinct from ADR and product/release vocabulary;
- Accepted distinct from Approved; and
- repository hardening/acceptance distinct from real-production launch approval.

## 7. Review and archival result

Current documentation is reviewed when a material change affects it rather than through mandatory calendar rewrites. Stale material is classified as Current, Historical evidence, Superseded, or Obsolete duplicate. Unique historical evidence is preserved; obsolete duplicate living narrative may be removed; current primary-document moves update owning indexes/links.

## 8. Non-brittle automation boundary

Final automation explicitly does not:

- parse every implementation method/class/import;
- infer ownership from raw dependency counts;
- require prose churn for harmless internal refactors;
- compare historical evidence to current test totals; or
- require one document per route/controller/test/class.

This boundary is normative and tested through the maintenance-standard contract.

## 9. Prior-phase preservation review

The final review confirms the repository retains the complete governance chain:

- P1 domain standard/coverage/exit;
- P2 security standard/coverage/exit;
- P3 operations standard/coverage/exit;
- P4 interface standard/coverage/exit;
- P5 testing/evidence standard/coverage/exit;
- P6 architecture/governance standard/coverage/exit;
- P7 maintenance standard/coverage/final DCP exit path;
- all 14 domain roots and P1–P5 living profile families;
- ADR/current architecture, dependency map, glossary, current capability matrix, and current audits;
- shared security/operations navigation; and
- production launch approval as a distinct external decision that remains not yet approved.

P1–P6 exact protected transition evidence remains recorded in their status/exit records rather than copied into living domain documentation.

## 10. P7 exit checklist

- [x] Maintenance standard adopted.
- [x] Final maintenance/final-acceptance inventory frozen.
- [x] Program plan standards catalog corrected and P7 maintenance wording reconciled.
- [x] Repository documentation standard points to maintenance standard.
- [x] Definition of Done points to change-time maintenance obligations.
- [x] Repository/product navigation indexes P7 maintenance/final evidence.
- [x] P6 final protected identity recorded in current status.
- [x] Final maintenance architecture test active.
- [x] Complete P0–P7 inventory review complete.
- [ ] Final DCP exit report created with exact content candidate identity.
- [ ] Exact P7 candidate/evidence head protected-green.
- [ ] Exact final DCP evidence/status head protected-green.

P7 content is complete. There is no P8. After both final protected gates close, future documentation work becomes normal change-driven maintenance under the standards created by DCP.
