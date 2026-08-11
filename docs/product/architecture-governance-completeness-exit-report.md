# DCP-P6 architecture and program-governance consolidation exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation  
**Status:** Complete — candidate gate passed; final transition validation pending  
**Content candidate SHA:** `3bf6b7a7479e64739c1d650bcb02ccbfba25ffdf`  
**Validated candidate/evidence SHA:** `b2d63ffceea50658c989a569a44ad98fc47db75a`

## 1. Outcome

The frozen P6 architecture/program-governance inventory is complete and the exact candidate/evidence head passed all protected candidate checks. P6 now provides one current system architecture entry point, explicit ADR lifecycle, a 14-domain supported dependency map, shared terminology, current architecture audits/capability navigation, clarified shared documentation ownership, and deterministic architecture-governance CI enforcement.

P7 is selected only after the resulting final P6 evidence/status head independently passes the same protected gate.

## 2. Governing standard

P6 adopted [Architecture and program-governance standard](architecture-governance-standard.md), defining system-level authority, required current architecture surfaces, ADR lifecycle/supersession, consumer→owner dependency semantics, supported-contract versus persistence-reach-through rules, shared versus domain documentation, current versus historical narrative, audit/capability/glossary obligations, obsolete narrative classification, and stable high-signal CI enforcement.

## 3. P5 entry gate

P6 began only after P5 final transition head `983b662bac8873ba2eb71ccec8a6c9e5d1331923` passed:

- Dependency Review `31516665602` — success;
- CodeQL `31516665615` — success;
- CI `31516665593` — success.

## 4. ADR consolidation

The [ADR/current architecture index](../adr/README.md) now acts as the system architecture entry point, links dependency/glossary/capability/operations/audits/domain owners, and defines ADR lifecycle states exactly as Proposed, Accepted, Superseded, Rejected.

ADR 0001–0008 remain Accepted. P6 introduced no new runtime ADR because this phase consolidated documentation/governance rather than changing runtime architecture. The ADR template now records related scope, optional supersession, supported boundaries, validation, revisit conditions, and explicit supersession handling.

## 5. Cross-domain dependency consolidation

[Cross-domain dependency map](cross-domain-dependency-map.md) represents all **14/14** canonical code domains exactly once as consumers.

Dependency notation is consumer → owning supported contract. The map records Identity/tenant authority, Alliances/Memberships/Authorization foundations, Audit/Platform shared boundaries, Content/Recruitment ownership, Events/Rallies/Notifications/Contributions collaboration, Integrations external machine boundary, Kingdoms neutral-reference versus tenant-observation boundaries, and prohibited persistence/ownership/exposure patterns.

Raw import counts are not architecture truth. Cross-domain collaboration is supported when ownership remains explicit; duplicate writable ownership and persistence reach-through remain defects.

## 6. Shared terminology

[Shared glossary](glossary.md) disambiguates high-risk identity/tenancy/Kingdoms/event/integration/evidence/status/production terms, including platform `Alliance` versus game-side `KingdomAlliance`, global User versus membership/authorization, internal outbox event versus externally eligible webhook event, living contract versus historical evidence, and repository hardening versus real production launch.

## 7. Architecture audit refresh

`repository-structure-audit.md` and `domain-boundary-audit.md` are now **Current** architecture evidence rather than migration-candidate reports. They reflect 14 canonical runtime domains, five top-level docs groups, P1–P5 living profile families, six PHPUnit suite roots, current Kingdoms K1–K3 ownership, Integrations/Platform/Audit boundaries, and current workflow ownership.

Historical pre-Kingdoms/migration context remains explicitly historical rather than being rewritten or deleted.

## 8. Shared ownership audit

P6 reviewed top-level `docs/product/`, `docs/security/`, and `docs/operations/` against the domain-first ownership model.

Result: **no further domain-specific relocation is required.** Shared roots remain cross-program/shared; domain current behavior/evidence remains under `docs/domains/<domain>/`.

## 9. Current capability/navigation refresh

`current-capability-matrix.md`, `docs/README.md`, `docs/product/README.md`, and `docs/adr/README.md` now consistently navigate current architecture/ADRs, dependency map, glossary, current capability/non-capability, domain owners, shared security/operations, audits, and production-launch boundary.

Explicit non-capabilities remain visible, including Kingdoms public API/webhooks/automated ingestion/scoring/automatic transfers, payment processing, support impersonation, generic Notifications transport, OTEL export, and real-production approval.

## 10. Historical narrative result

Accepted Phase 0–6, Kingdoms increment, ADR, and DCP evidence remains historical evidence. Current audit wording was refreshed where migration-era language was no longer correct, but no unique accepted historical evidence was deleted or rewritten into present-tense runtime truth.

## 11. CI enforcement

`tests/Architecture/ArchitectureGovernanceDocumentationTest.php` verifies required P6 artifacts, ADR lifecycle/index integrity, template supersession rules, 14-domain dependency parity and owner files, high-risk glossary terms, current audit status, shared documentation ownership, and current navigation.

Existing suites continue P1–P5 documentation, ownership, local-link, interface, security, operations, testing/evidence, structure, and domain-boundary enforcement.

## 12. Candidate validation result

Exact candidate/evidence head:

`b2d63ffceea50658c989a569a44ad98fc47db75a`

Protected workflows:

- Dependency Review `31518789039` — **success**;
- CodeQL `31518789038` — **success**;
- CI `31518789030` — **success**.

CI evidence on that exact candidate:

- frontend dependency/quality/build — success;
- PostgreSQL migrations — success;
- Pint — **487 files**;
- PHPStan/Larastan — **345/345, 0 errors**;
- ParaTest/PHPUnit — **395 tests / 9,104 assertions**;
- P6 architecture-governance assertions — success;
- repository-wide Markdown-link and prior DCP architecture checks — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success.

## 13. Frozen inventory result

The [P6 coverage matrix](architecture-governance-coverage-matrix.md) is 100% content-complete and the candidate protected gate is green. The only remaining P6 requirement is independent protected validation of the final evidence/status transition head created by recording this acceptance and selecting P7 conditionally.

## 14. Final transition gate

Before P7 implementation begins, the exact final P6 evidence/status head must independently pass:

1. protected Dependency Review;
2. protected CodeQL; and
3. complete CI, including frontend, PHP/documentation architecture/link tests, immutable image, staging, backup/restore, and scan.

If that head fails, P6 remains effective and only the final-gate defect may be repaired. If it passes, P6 is fully closed and P7 becomes authoritative without another P6 transition commit.
