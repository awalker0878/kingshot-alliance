# Documentation maintenance and final-acceptance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P7` — Maintenance automation and final acceptance  
**Inventory state:** Frozen — implementation in progress

## 1. Purpose

This is the authoritative final DCP inventory. P7 does not create another domain-content layer; it proves P1–P6 completeness remains protected by stable change-driven maintenance rules and deterministic CI.

The governing rules are in [Documentation maintenance standard](documentation-maintenance-standard.md).

## 2. Required P7 artifacts

| Artifact/work item | Required purpose | Status |
| --- | --- | --- |
| `documentation-maintenance-standard.md` | change-time obligations, evidence/status/navigation/archival/automation lifecycle | Complete |
| this coverage matrix | frozen final maintenance/final-acceptance inventory | Complete |
| `documentation-program-plan.md` | standards catalog uses actual current filenames and P7 scope agrees with maintenance standard | Normalize |
| `documentation-standard.md` | points normal maintenance to P7 standard | Normalize |
| `definition-of-done.md` | links maintenance obligations into normal change acceptance | Normalize |
| `docs/README.md` | exposes maintenance governance after program completion | Normalize |
| `docs/product/README.md` | indexes P7 standard/matrix/final exit and post-program state | Normalize |
| `documentation-program-status.md` | P6 final acceptance identity + current P7 state | Normalize |
| final DCP exit report | records P0–P7 completion and exact protected evidence | Create before candidate gate |
| final maintenance architecture test | aggregates stable P1–P7 invariants without brittle implementation parsing | Create |
| complete final inventory review | verify all prior standards/matrices/exits/current navigation remain present/non-conflicting | Required before candidate freeze |

## 3. Stable automation inventory

Final P7 automation must retain or verify these high-signal rules:

- exactly five top-level documentation groups;
- canonical code-domain/docs-domain parity for all 14 domains;
- one code-local README and canonical domain contract per code domain;
- required P1–P5 specialized domain profiles remain present;
- code-local README links to canonical domain documentation;
- local Markdown link integrity;
- no flat living Markdown under `docs/domains/` except `README.md`;
- lowercase kebab naming rules and accepted exceptions;
- domain-specific evidence does not drift back into shared roots;
- current ADR index/status lifecycle remains valid;
- P6 dependency/glossary/current-audit navigation remains present;
- every DCP specialized standard P1–P7 is indexed using its actual filename;
- every prior DCP phase standard/coverage/exit artifact remains discoverable;
- current maintenance and Definition of Done navigation agree; and
- DCP final status/exit records distinguish repository completion from real-production approval.

Existing P1–P6 tests remain active; P7 adds an aggregate maintenance test for cross-standard/index consistency rather than duplicating every detailed assertion.

## 4. Standards catalog inventory

Required specialized standards:

| Phase | Canonical file | Status |
| --- | --- | --- |
| P1 | `domain-contract-standard.md` | Current |
| P2 | `security-documentation-standard.md` | Current |
| P3 | `operations-documentation-standard.md` | Current |
| P4 | `interface-documentation-standard.md` | Current |
| P5 | `testing-evidence-standard.md` | Current |
| P6 | `architecture-governance-standard.md` | Current |
| P7 | `documentation-maintenance-standard.md` | Current |

P7 audit finding: the program plan currently names the P5 standard as `testing-evidence-documentation-standard.md`; the actual canonical file is `testing-evidence-standard.md`. P7 must correct that stale catalog entry.

## 5. Change-time obligation inventory

The maintenance standard must cover material changes to:

- domain ownership/model/invariants/lifecycle/contracts;
- security/privacy/tenancy/authentication/authorization/destructive behavior;
- persistent/async/runtime/diagnostic/recovery/rollback/capacity behavior;
- HTTP/UI/API/CLI/event/job/webhook/import/export/media/external integrations;
- validation/evidence/performance/migration/accessibility acceptance;
- durable architecture/ADR/dependency/shared terminology;
- product capability/status/named increments;
- shared runtime/security/production controls; and
- documentation structure/ownership/standards themselves.

Coverage: **complete** in `documentation-maintenance-standard.md`.

## 6. Evidence and status lifecycle inventory

P7 must preserve:

- living current documentation updated with current behavior;
- historical acceptance evidence immutable except labeled factual hardening/navigation repair;
- historical test counts/run identities retained as historical;
- DCP status vocabulary separate from ADR and product/release vocabularies;
- Accepted separate from Approved; and
- repository hardening/acceptance separate from real-production launch approval.

Coverage: **complete** in the maintenance standard; final status/exit records remain to be produced.

## 7. Review and archival inventory

Required final rules:

- review current documentation when a material change affects it rather than forcing calendar rewrites;
- classify stale material as Current, Historical evidence, Superseded, or Obsolete duplicate;
- preserve historical decision/acceptance value;
- remove obsolete duplicate living narrative rather than retaining conflicting stubs; and
- update owning indexes whenever current primary documents move/change.

Coverage: **complete** in the maintenance standard.

## 8. Non-brittle automation boundary

P7 automation must not:

- parse every implementation method/class/import;
- infer ownership from raw dependency counts;
- require prose churn for harmless internal refactors;
- compare historical evidence to current test totals; or
- require one document per route/controller/test/class.

This constraint is part of the final acceptance criteria, not optional guidance.

## 9. Prior-phase preservation inventory

P7 final validation must confirm the repository still exposes:

- P1 domain standard/coverage/exit;
- P2 security standard/coverage/exit;
- P3 operations standard/coverage/exit;
- P4 interface standard/coverage/exit;
- P5 testing/evidence standard/coverage/exit;
- P6 architecture/governance standard/coverage/exit;
- P7 maintenance standard/coverage/final DCP exit;
- all 14 domain roots and P1–P5 profiles;
- ADR/current architecture, dependency map, glossary, current capability matrix, audits;
- shared security/operations navigation; and
- production launch approval as a distinct not-yet-approved external decision.

## 10. P7 exit checklist

- [x] Maintenance standard adopted.
- [x] Final maintenance/final-acceptance inventory frozen.
- [ ] Program plan standards catalog corrected and P7 maintenance wording reconciled.
- [ ] Repository documentation standard points to maintenance standard.
- [ ] Definition of Done points to change-time maintenance obligations.
- [ ] Repository/product navigation indexes P7 maintenance/final evidence.
- [ ] P6 final protected identity recorded in current status.
- [ ] Final maintenance architecture test active.
- [ ] Complete P0–P7 inventory review complete.
- [ ] Final DCP exit report created.
- [ ] Exact P7 candidate/evidence head protected-green.
- [ ] Exact final DCP evidence/status head protected-green.

There is no P8. After these gates close, future documentation work becomes normal change-driven maintenance under the standards created by DCP.
