# Architecture and program-governance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation  
**Inventory state:** Frozen — 100% required content implemented; candidate evidence preparation in progress

## 1. Purpose

This is the authoritative P6 completion inventory. P6 consolidates system-level architecture/governance after P1–P5 established complete domain, security, operations, interface, and testing/evidence ownership.

The governing rules are in [Architecture and program-governance standard](architecture-governance-standard.md).

## 2. Required P6 living artifacts

| Artifact | Required purpose | P6 status |
| --- | --- | --- |
| `architecture-governance-standard.md` | normative architecture/governance ownership, ADR lifecycle, current-vs-historical rules | Complete |
| `cross-domain-dependency-map.md` | 14-domain supported dependency/collaboration map | Complete |
| `glossary.md` | shared ambiguous terminology | Complete |
| `docs/adr/README.md` | current system architecture + indexed ADR lifecycle | Complete |
| `current-capability-matrix.md` | current capability/non-capability/status navigation | Complete |
| `repository-structure-audit.md` | current physical architecture evidence | Complete |
| `domain-boundary-audit.md` | current semantic boundary evidence | Complete |
| `docs/product/README.md` | authoritative program navigation | Complete |
| `docs/README.md` | repository documentation navigation | Complete |
| `documentation-program-status.md` | P6 current control state | Complete |
| P6 exit report | immutable P6 scope/validation record | Candidate preparation |
| P6 architecture test | deterministic high-signal governance checks | Complete |

No per-domain P6 profile is required. P6 consumes the already-complete P1–P5 domain profile families.

## 3. ADR inventory

| ADR | Decision | Current status | P6 result |
| --- | --- | --- | --- |
| 0001 | Enterprise modular monolith | Accepted | Retained/indexed |
| 0002 | Alliance-level tenancy | Accepted | Retained/indexed |
| 0003 | First-party authentication | Accepted | Retained/indexed |
| 0004 | Queues and transactional outbox | Accepted | Retained/indexed |
| 0005 | S3-compatible object storage | Accepted | Retained/indexed |
| 0006 | Observability and correlation | Accepted | Retained/indexed |
| 0007 | Testing toolchain compatibility | Accepted | Retained/indexed |
| 0008 | Domain-first source layout | Accepted | Retained/indexed |

P6 introduces no new architecture decision because consolidation does not change accepted runtime architecture. ADR lifecycle is now explicit: Proposed, Accepted, Superseded, Rejected. The ADR template defines supersession handling and the index exposes status/current authority.

## 4. Canonical domain dependency coverage

The [cross-domain dependency map](cross-domain-dependency-map.md) represents exactly these 14 code domains once as consumers:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, `Recruitment`.

Coverage: **14/14**.

Dependency notation is consumer → owning supported contract. P6 intentionally does not freeze raw import counts.

## 5. High-risk architecture boundary coverage

| Boundary | Current owner/rule | P6 surfaces |
| --- | --- | --- |
| global User vs Alliance access | Identity assurance + Alliances/Memberships/Authorization tenant authority | dependency map, glossary, ADR view |
| Platform admin vs Alliance role | Platform grant distinct from Authorization Alliance role | dependency map, glossary, boundary audit |
| tenant identity/context | Alliances owns active tenant; Memberships validates active relation | dependency map, boundary audit |
| Content vs Recruitment availability | Recruitment owns state; Content presents only | dependency map, boundary audit |
| Events/Rallies | Events owns occurrence/attendance; Rallies owns Rally coordination | dependency map, boundary audit |
| Events/Notifications | Events owns source facts; Notifications owns reminder state | dependency map, boundary audit |
| Events/Contributions | Events owns attendance; Contributions reconciles/derives | dependency map, boundary audit |
| Contributions/Notifications | Contributions owns report semantics; Notifications coordinates due requests | dependency map, boundary audit |
| Audit vs outbox | Audit owns attributable evidence; Platform owns asynchronous infrastructure | dependency map, glossary, boundary audit |
| producer event vs public webhook | producer owns business semantics; Integrations owns eligibility/delivery | dependency map, glossary, ADR view |
| Alliance vs KingdomAlliance | platform tenant vs neutral game-side reference | glossary, dependency map, capability matrix |
| neutral Kingdoms reference vs tenant observation | shared identity grants no tenant-state access | glossary, dependency map, boundary audit |
| repository hardening vs production approval | repository evidence cannot prove external production controls | glossary, ADR view, capability matrix |

All frozen high-risk boundaries have current system-level visibility.

## 6. Shared top-level ownership audit

P6 confirms:

- `docs/product/` contains cross-program scope/governance/current-state navigation, DCP standards/evidence, architecture audits, historical phase-wide acceptance, hardening/production decisions;
- `docs/security/` contains shared security baseline, historical phase-wide threat models, production security boundary;
- `docs/operations/` contains shared runtime/configuration/observability/deployment/recovery/runbooks and historical phase-wide operating evidence;
- single-domain current implementation/security/operations/interfaces/testing/product evidence remains under `docs/domains/<domain>/`.

Result: **no additional domain-specific relocation is required** by P6. Earlier P1–P3 ownership migration already established the correct split.

## 7. Current-state navigation result

Current paths are complete:

- `docs/README.md` → current architecture, dependency map, glossary, capability matrix, domain/product/security/operations navigation;
- `docs/adr/README.md` → all numbered ADRs, lifecycle, dependency map, glossary, current architecture context;
- `docs/product/README.md` → P1–P6 standards/matrices/evidence plus current architecture/dependency/glossary/audits/capability navigation;
- `current-capability-matrix.md` → ADR view, dependency map, glossary, owner contracts;
- architecture audits → current normative owners and dependency map;
- status ledger → exact P5 accepted transition identity and P6 control state.

## 8. Terminology result

The [shared glossary](glossary.md) now disambiguates all frozen high-risk terms, including User/Alliance/Active Alliance/Membership/permission/Platform administrator; Kingdom/KingdomPlayer/KingdomAlliance/TrackedKingdomAlliance/neutral references; Event/Rally/reminders/contribution report requests; transactional outbox/internal versus external webhook event/API credential/idempotency; living contract/historical evidence/ADR/supported contract/persistence reach-through; repository/product/DCP status vocabulary; and repository hardening versus real production launch.

## 9. Historical and obsolete narrative result

- Phase 0–6 and named increment/DCP acceptance records remain historical evidence.
- Migration-era statements may remain only inside explicitly historical context.
- `repository-structure-audit.md` and `domain-boundary-audit.md` are now current system audits and no longer carry migration-candidate status.
- Accepted ADR rationale remains historical decision evidence if later superseded.
- No accepted historical evidence deletion is required.
- No obsolete duplicate living architecture tree was found or created.

## 10. P6 architecture enforcement

`tests/Architecture/ArchitectureGovernanceDocumentationTest.php` verifies:

- required P6 living artifacts exist;
- every numbered ADR is indexed and uses allowed lifecycle status;
- ADR template starts Proposed and contains supersession handling;
- dependency inventory contains every canonical code domain exactly once and real code/doc owner paths exist;
- glossary contains high-risk shared terms;
- current architecture audits are Current and not migration-candidate records;
- shared product/security/operations indexes retain program/shared ownership and domain navigation; and
- current docs/product/capability/ADR navigation links dependency and glossary surfaces.

Existing repository architecture checks continue local Markdown-link, domain parity, ownership, P1–P5 profile, and other structural enforcement.

## 11. P6 exit checklist

- [x] Architecture/program-governance standard adopted.
- [x] 14-domain dependency inventory frozen and implemented.
- [x] Shared terminology inventory frozen and implemented.
- [x] ADR inventory/lifecycle normalized.
- [x] Shared top-level ownership audit completed; no further relocation required.
- [x] Historical/obsolete narrative classification completed.
- [x] Cross-domain dependency map complete and navigated.
- [x] Glossary complete and navigated.
- [x] Current architecture audits refreshed.
- [x] Capability/status navigation refreshed.
- [x] Product/docs navigation refreshed.
- [x] P6 architecture enforcement active.
- [x] Complete P6 content/ownership inventory review completed.
- [ ] Exact P6 candidate/evidence head protected-green.
- [ ] P6 final evidence/status head protected-green.

P6 content is complete. P7 remains blocked until both protected gates close.
