# DCP-P6 architecture and program-governance consolidation exit report

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase exit report  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation  
**Status:** Candidate — protected validation pending  
**Content candidate SHA:** `3bf6b7a7479e64739c1d650bcb02ccbfba25ffdf`

## 1. Outcome

The frozen P6 architecture/program-governance inventory is fully implemented. P6 now provides one current system architecture entry point, explicit ADR lifecycle, a 14-domain supported dependency map, shared terminology, current architecture audits/capability navigation, clarified shared documentation ownership, and deterministic architecture-governance CI enforcement.

P7 remains blocked until this candidate/evidence head passes protected Dependency Review, CodeQL, and complete CI and the resulting final P6 evidence/status head independently passes the same protected gate.

## 2. Governing standard

P6 adopted [Architecture and program-governance standard](architecture-governance-standard.md), defining:

- system-level authority/source-of-truth precedence;
- required current architecture/governance surfaces;
- ADR lifecycle and supersession rules;
- consumer→owner dependency semantics;
- supported-contract versus persistence-reach-through rules;
- shared program versus domain-owned documentation boundaries;
- living-current versus historical-evidence treatment;
- current architecture audit requirements;
- current capability/status navigation rules;
- shared terminology criteria;
- obsolete narrative classification; and
- stable high-signal P6 CI enforcement.

## 3. P5 entry gate

P6 began only after the exact P5 final transition head:

`983b662bac8873ba2eb71ccec8a6c9e5d1331923`

passed:

- Dependency Review `31516665602` — success;
- CodeQL `31516665615` — success;
- CI `31516665593` — success.

This made P6 authoritative without bypassing the P5 second protected gate.

## 4. ADR consolidation

The [ADR/current architecture index](../adr/README.md) now:

- acts as the current system-level architecture entry point;
- links dependency map, glossary, capability matrix, operations, audits, and domain owners;
- defines ADR lifecycle states exactly as Proposed, Accepted, Superseded, Rejected;
- indexes all current numbered ADRs with status; and
- distinguishes internal outbox events, external webhook eligibility, tenancy, neutral Kingdoms references, and production evidence boundaries.

ADR 0001–0008 remain Accepted. P6 introduces no new ADR because consolidation changes documentation governance rather than runtime architecture.

The ADR template now records related scope, optional supersession, supported boundaries, validation, revisit conditions, and explicit supersession handling.

## 5. Cross-domain dependency consolidation

[Cross-domain dependency map](cross-domain-dependency-map.md) represents all **14/14** canonical code domains exactly once as consumers.

Dependency notation is consumer → owning supported contract. The map documents:

- Identity versus tenant authority;
- Alliances/Memberships/Authorization foundation;
- Audit and Platform shared infrastructure/evidence boundaries;
- Content/Recruitment ownership;
- Events/Rallies/Notifications/Contributions collaboration;
- Integrations external machine boundary;
- Kingdoms neutral-reference versus tenant-observation boundaries; and
- prohibited persistence/ownership/exposure patterns.

P6 intentionally does not freeze raw import counts. Cross-domain imports/collaboration are valid when they use intentional owner contracts; duplicate writable ownership and persistence reach-through remain defects.

## 6. Shared terminology

[Shared glossary](glossary.md) now disambiguates terms where inconsistent use changes architecture/security meaning, including:

- User, Alliance, Active Alliance, Membership, roles/permissions, Platform administrator;
- Kingdom, KingdomPlayer, KingdomAlliance, TrackedKingdomAlliance, neutral references, stable game identifiers;
- Event, Rally, reminder delivery, scheduled contribution-report requests, Contributions;
- transactional outbox, internal events, externally eligible webhook events, API credentials, idempotency;
- living contracts, historical evidence, ADRs, supported contracts, persistence reach-through;
- Implemented/Accepted/Approved/Candidate/Validated/Not implemented/Not yet approved; and
- repository-controlled production hardening versus real production launch.

## 7. Architecture audit refresh

`repository-structure-audit.md` and `domain-boundary-audit.md` are now **Current** architecture evidence rather than migration-candidate reports.

The structure audit reflects:

- 14 canonical runtime domains;
- five top-level docs groups;
- mirrored domain docs ownership;
- all five P1–P5 living domain profile families;
- six canonical PHPUnit suite roots; and
- current shared/program architecture ownership.

The boundary audit reflects current accepted ownership, including Kingdoms K1–K3 runtime, Integrations external exposure boundaries, Platform/Audit separation, tenant authority, and Events/Rallies/Notifications/Contributions workflow ownership.

Historical 2026-08-08 pre-Kingdoms/migration context remains explicitly historical rather than being rewritten or deleted.

## 8. Shared ownership audit

P6 reviewed top-level `docs/product/`, `docs/security/`, and `docs/operations/` against the completed domain-first ownership model.

Result: **no further domain-specific relocation is required.**

- product remains cross-program governance/current-state/audits/historical phase-wide evidence/production decisions;
- security remains shared baseline/historical phase-wide threat evidence/production security boundary;
- operations remains shared runtime/configuration/observability/deployment/recovery/runbooks/historical phase-wide operating evidence;
- domain-specific current behavior/evidence remains under `docs/domains/<domain>/`.

P6 therefore consolidates authority without re-migrating accepted historical evidence.

## 9. Current capability/navigation refresh

`current-capability-matrix.md`, `docs/README.md`, `docs/product/README.md`, and `docs/adr/README.md` now provide consistent navigation among:

- current architecture/ADRs;
- dependency map;
- glossary;
- current capability/non-capability;
- domain owners;
- shared security/operations;
- architecture audits; and
- production launch boundary.

The current capability matrix preserves explicit non-capabilities such as Kingdoms public API/webhooks/automated ingestion/scoring/automatic transfers, payment processing, support impersonation, generic Notifications transport, OTEL export, and real-production approval.

## 10. Historical narrative result

P6 classified old narrative rather than cosmetically rewriting it:

- accepted Phase 0–6, Kingdoms increment, and DCP exit/validation records remain historical evidence;
- accepted ADR rationale remains durable decision evidence;
- old migration/pre-Kingdoms facts remain only when explicitly historical;
- current audit status/narrative was refreshed where migration-era wording was no longer correct; and
- no unique accepted historical evidence was deleted.

## 11. CI enforcement

`tests/Architecture/ArchitectureGovernanceDocumentationTest.php` verifies:

- required P6 artifacts;
- ADR index/status lifecycle integrity;
- ADR template lifecycle/supersession handling;
- 14-domain dependency inventory parity and real owner files;
- required high-risk glossary terms;
- current audit status and dependency-map linkage;
- shared product/security/operations ownership statements; and
- current navigation to dependency/glossary architecture surfaces.

Existing architecture suites continue P1–P5 documentation, ownership, local-link, interface, security, operations, testing/evidence, physical structure, and domain-boundary enforcement.

## 12. Frozen inventory result

The [P6 coverage matrix](architecture-governance-coverage-matrix.md) is 100% content-complete:

- architecture/governance standard — complete;
- ADR lifecycle/index — complete;
- dependency map — 14/14 complete;
- glossary — complete;
- shared ownership audit — complete, no relocation required;
- current audits — complete;
- capability/status/navigation refresh — complete;
- P6 architecture enforcement — complete;
- historical/obsolete narrative review — complete.

Only the protected candidate/final-head gates remain.

## 13. Candidate validation gate

Before this report becomes Complete:

1. the exact candidate/evidence head containing this report, frozen matrix, and status ledger must pass protected Dependency Review;
2. protected CodeQL must pass;
3. complete CI must pass, including Pint, PHPStan/Larastan, full PHPUnit suites, P6 architecture-governance tests, and repository-wide Markdown-link validation;
4. immutable image, staging, backup/restore, and image scan must pass where included;
5. exact candidate workflow identities must be recorded; and
6. the resulting final P6 evidence/status head must independently pass the same protected gate before P7 becomes authoritative.

Until then, the correct `continue` decision remains **finish DCP-P6**.
