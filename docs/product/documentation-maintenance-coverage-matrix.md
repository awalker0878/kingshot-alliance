# Documentation maintenance and final-acceptance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P7` — Maintenance automation and final acceptance  
**Inventory state:** Complete — candidate gate passed; final evidence/status validation pending  
**Content candidate SHA:** `4c3091f8ae92ee450ff3a9ee23df65ab4f193636`  
**Validated candidate/evidence SHA:** `9676eadc618c2892d05fcf12bf4529c8781a12f7`

## 1. Purpose

This is the authoritative final DCP inventory. P7 proves P1–P6 completeness remains protected by stable change-driven maintenance rules and deterministic CI rather than creating another domain-content layer.

Governing rules: [Documentation maintenance standard](documentation-maintenance-standard.md). Final evidence: [DCP final exit report](documentation-completion-program-exit-report.md).

## 2. Required P7 artifacts

All frozen P7 work is complete: maintenance standard, coverage matrix, normalized program plan/standards catalog, maintenance-aware repository documentation standard and Definition of Done, repository/product navigation, current status ledger, final DCP exit report, final aggregate maintenance architecture test, and complete P0–P7 inventory review.

## 3. Stable automation result

The final gate plus existing P1–P6 suites protect exactly five top-level documentation groups; 14/14 code-domain/docs-domain parity; code-local/canonical contract/profile parity; canonical documentation links; local Markdown links; domain evidence placement; ADR lifecycle/indexing; P6 architecture/dependency/glossary/audit navigation; all P1–P7 standards by canonical filename; all P1–P7 standards/coverage/exit governance; maintenance/Definition-of-Done navigation; and DCP completion versus production-approval separation.

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

The maintenance standard covers all material domain, security/privacy, operations, interface/integration, validation/evidence, architecture, product/status, shared runtime/security/production, and documentation-structure changes. It preserves living-current versus historical evidence semantics, distinct DCP/ADR/product status vocabularies, Accepted versus Approved, and repository hardening versus real-production approval.

Stale material is classified as Current, Historical evidence, Superseded, or Obsolete duplicate. Current documents are reviewed when material changes affect them rather than through forced calendar rewrites.

## 6. Non-brittle automation boundary

Final automation does **not** parse every method/class/import, infer ownership from raw dependency counts, require prose churn for harmless refactors, compare historical evidence to current test totals, or require one document per route/controller/test/class.

## 7. Prior-phase preservation review

The final inventory retains P1–P7 standards/coverage/exit governance, all 14 domain roots/P1–P5 profiles, ADR/current architecture, dependency map, glossary, current capability/audits, shared security/operations, and production launch approval as a distinct not-yet-approved decision.

## 8. Candidate validation result

Exact P7 candidate/evidence head `9676eadc618c2892d05fcf12bf4529c8781a12f7` passed:

- Dependency Review `31520665029` — success;
- CodeQL `31520665079` — success;
- CI `31520665030` — success.

CI included **488 Pint files**, PHPStan/Larastan **345/345 with 0 errors**, **401 tests / 9,312 assertions**, frontend build, PostgreSQL migrations, all P1–P7 documentation/maintenance checks, repository links, immutable image, staging, backup/restore, and image scan.

## 9. P7 exit checklist

- [x] Maintenance standard adopted.
- [x] Final maintenance/final-acceptance inventory frozen.
- [x] Program plan standards catalog corrected.
- [x] Repository documentation standard linked to maintenance.
- [x] Definition of Done linked to change-time obligations.
- [x] Repository/product navigation updated.
- [x] P6 final protected identity recorded.
- [x] Final maintenance architecture test active.
- [x] Complete P0–P7 inventory review complete.
- [x] Final DCP exit report created.
- [x] Exact P7 candidate/evidence head protected-green.
- [ ] Exact final DCP evidence/status head protected-green.

P7 candidate acceptance is complete. There is no P8. When the exact final evidence/status transition head passes the second protected gate, DCP is fully Complete and future documentation work is normal change-driven maintenance.
