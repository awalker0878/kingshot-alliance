# Architecture and program-governance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation  
**Inventory state:** Frozen — implementation in progress

## 1. Purpose

This is the authoritative P6 completion inventory. P6 consolidates system-level architecture/governance only after P1–P5 established complete domain, security, operations, interface, and testing/evidence ownership.

The governing rules are in [Architecture and program-governance standard](architecture-governance-standard.md).

## 2. Required P6 living artifacts

| Artifact | Required purpose | P6 status |
| --- | --- | --- |
| `architecture-governance-standard.md` | normative architecture/governance ownership, ADR lifecycle, current-vs-historical rules | Complete |
| `cross-domain-dependency-map.md` | 14-domain supported dependency/collaboration map | Complete |
| `glossary.md` | shared ambiguous terminology | Complete |
| `docs/adr/README.md` | current system architecture + indexed ADR lifecycle | Normalize |
| `current-capability-matrix.md` | current capability/non-capability/status navigation | Refresh |
| `repository-structure-audit.md` | current physical architecture evidence | Refresh migration-era status |
| `domain-boundary-audit.md` | current semantic boundary evidence | Refresh migration-era status and link dependency map |
| `docs/product/README.md` | authoritative program navigation | Add P6 surfaces/status |
| `docs/README.md` | repository documentation navigation | Add system architecture/glossary path if missing |
| `documentation-program-status.md` | P6 current control state | Update after inventory implementation |
| P6 exit report | immutable P6 scope/validation record | Create before candidate gate |
| P6 architecture test | deterministic high-signal governance checks | Create |

No per-domain P6 profile is required. P6 consumes the already-complete P1–P5 domain profile families.

## 3. ADR inventory

Current ADR files are:

| ADR | Decision | Current status | P6 action |
| --- | --- | --- | --- |
| 0001 | Enterprise modular monolith | Accepted | Retain/index |
| 0002 | Alliance-level tenancy | Accepted | Retain/index |
| 0003 | First-party authentication | Accepted | Retain/index |
| 0004 | Queues and transactional outbox | Accepted | Retain/index |
| 0005 | S3-compatible object storage | Accepted | Retain/index |
| 0006 | Observability and correlation | Accepted | Retain/index |
| 0007 | Testing toolchain compatibility | Accepted | Retain/index |
| 0008 | Domain-first source layout | Accepted | Retain/index |

P6 introduces no new architecture decision because the consolidation work does not change accepted runtime architecture. It standardizes the lifecycle vocabulary and indexing rules.

Required ADR statuses are exactly Proposed, Accepted, Superseded, or Rejected.

## 4. Canonical domain dependency coverage

The [cross-domain dependency map](cross-domain-dependency-map.md) must represent every code-local domain map exactly once as a consumer:

- Alliances
- Audit
- Authorization
- Content
- Contributions
- Events
- Identity
- Integrations
- Kingdoms
- Memberships
- Notifications
- Platform
- Rallies
- Recruitment

Coverage requirement: **14/14**.

P6 records supported owner contracts and semantic direction rather than freezing raw import counts.

## 5. High-risk architecture boundaries requiring system-level visibility

| Boundary | Current owner/rule | Required P6 surface |
| --- | --- | --- |
| global User vs Alliance tenant access | Identity → Alliances/Memberships/Authorization | dependency map + glossary + ADR view |
| Platform admin vs Alliance role | Platform distinct from Authorization Alliance roles | dependency map + glossary + boundary audit |
| tenant identity/context | Alliances owns active tenant; Memberships validates active relation | dependency map + boundary audit |
| Content vs Recruitment availability | Recruitment owns state; Content displays only | dependency map + boundary audit |
| Events/Rallies | Events owns occurrence/attendance; Rallies owns Rally coordination | dependency map + boundary audit |
| Events/Notifications | Events owns source facts; Notifications owns durable reminder state | dependency map + boundary audit |
| Events/Contributions | Events owns attendance; Contributions reconciles/derives | dependency map + boundary audit |
| Contributions/Notifications | Contributions owns report semantics; Notifications coordinates due requests | dependency map + boundary audit |
| Audit vs outbox | Audit owns attributable evidence; Platform owns transport infrastructure | dependency map + glossary + boundary audit |
| producer event vs public webhook | producer owns business semantics; Integrations approves/delivers external events | dependency map + glossary + ADR view |
| Alliance vs KingdomAlliance | platform tenant vs neutral game-side reference | glossary + dependency map + current capability matrix |
| neutral Kingdoms reference vs tenant-owned observation | shared identity grants no tenant access | glossary + dependency map + boundary audit |
| repository hardening vs production approval | repository evidence cannot prove external production controls | glossary + ADR/current capability navigation |

## 6. Shared top-level ownership audit

### `docs/product/`

Allowed/current shared categories:

- completed Phase 0–6 baseline/governance;
- DCP standards, matrices, status, and exit reports;
- current repository-wide capability/architecture navigation;
- repository/domain architecture audits;
- historical phase-wide acceptance/accessibility evidence;
- production hardening and production approval/go-no-go records.

Single-domain current implementation detail belongs under `docs/domains/<domain>/`.

### `docs/security/`

Allowed/current shared categories:

- shared security baseline;
- historical cross-phase threat models;
- production-launch security boundary.

Domain living security reviews remain under the owning domain.

### `docs/operations/`

Allowed/current shared categories:

- runtime configuration/background processing/observability;
- deployment/release/rollback/backup/incident runbooks;
- historical phase-wide operations/migration evidence.

Domain diagnostics/recovery semantics remain under the owning domain.

P6 audit result before candidate freeze: no new domain-specific file relocation is required by the frozen inventory; P1–P3 already established the correct ownership split. P6 will enforce/clarify the boundary rather than re-migrate accepted historical evidence.

## 7. Current-state navigation inventory

Required current navigation relationships:

- `docs/README.md` → ADR/current architecture, domain index, product program navigation, shared security/operations;
- `docs/adr/README.md` → all eight ADRs, dependency map, glossary, current audits/capability matrix;
- `docs/product/README.md` → P1–P6 standards/matrices/exit evidence plus dependency map/glossary/current audits;
- `current-capability-matrix.md` → ADR view, dependency map, glossary, owning domain contracts;
- audits → normative architecture owners and dependency map;
- status ledger → exact P5 accepted transition identity and current P6 state.

## 8. Terminology inventory

P6 glossary must disambiguate at minimum:

- User;
- Alliance;
- Active Alliance;
- Membership;
- role/permission;
- Platform administrator;
- Kingdom;
- KingdomPlayer;
- KingdomAlliance;
- TrackedKingdomAlliance;
- neutral reference;
- stable game identifier;
- Event;
- Rally;
- reminder delivery;
- scheduled contribution report request;
- Contribution;
- transactional outbox;
- internal domain/outbox event;
- externally eligible webhook event;
- API credential;
- idempotency;
- living contract;
- historical evidence;
- ADR;
- supported contract;
- persistence reach-through;
- Implemented / Accepted / Approved / Candidate / Validated / Not implemented / Not yet approved;
- repository-controlled production hardening; and
- real production launch.

## 9. Historical/obsolete narrative audit

P6 rules:

- Phase 0–6 and named increment/DCP exit evidence remains historical evidence.
- Migration-era statements preserved inside audit history sections may remain when explicitly labeled historical.
- Current audit headers/statuses must not describe themselves as migration candidates.
- Accepted ADR rationale remains historical decision evidence even if later superseded.
- Obsolete duplicate living narrative may be removed only when it has no unique evidence value and ownership is deterministic elsewhere.

Frozen P6 decision: no accepted historical evidence deletion is required.

## 10. P6 architecture enforcement inventory

The P6 architecture test must verify:

- required P6 artifacts exist;
- eight current numbered ADRs are indexed and use allowed status vocabulary;
- ADR template starts Proposed and documents lifecycle-compatible metadata;
- dependency map contains all 14 canonical code domains exactly once in its canonical inventory table;
- dependency map links governing standard/current owner surfaces;
- glossary includes high-risk disambiguation terms;
- shared product/security/operations indexes state program/shared ownership and point domain-specific readers back to domain owners;
- repository/domain audit headers are Current and no longer use `Current migration audit` status;
- current capability matrix links architecture/dependency/glossary surfaces; and
- existing repository-wide local Markdown link validation remains green.

## 11. P6 exit checklist

- [x] Architecture/program-governance standard adopted.
- [x] 14-domain dependency inventory frozen.
- [x] Shared terminology inventory frozen.
- [x] ADR inventory/lifecycle requirement frozen.
- [x] Shared top-level ownership audit frozen.
- [x] Historical/obsolete narrative classification frozen.
- [ ] ADR index/lifecycle normalized.
- [ ] Cross-domain dependency map complete and navigated.
- [ ] Glossary complete and navigated.
- [ ] Current architecture audits refreshed.
- [ ] Capability/status navigation refreshed.
- [ ] Product/docs navigation refreshed.
- [ ] P6 architecture enforcement active.
- [ ] Complete P6 inventory review completed.
- [ ] Exact P6 candidate/evidence head protected-green.
- [ ] P6 final evidence/status head protected-green.

P7 remains blocked until both P6 protected gates close.
