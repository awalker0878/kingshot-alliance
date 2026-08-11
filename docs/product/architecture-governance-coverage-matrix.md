# Architecture and program-governance coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P6` — Architecture and program-governance consolidation  
**Inventory state:** Complete — candidate gate passed; final transition validation pending  
**Content candidate SHA:** `3bf6b7a7479e64739c1d650bcb02ccbfba25ffdf`  
**Validated candidate/evidence SHA:** `b2d63ffceea50658c989a569a44ad98fc47db75a`

## 1. Purpose

This is the authoritative P6 completion inventory. P6 consolidates system-level architecture/governance after P1–P5 established complete domain, security, operations, interface, and testing/evidence ownership.

The governing rules are in [Architecture and program-governance standard](architecture-governance-standard.md). Candidate acceptance evidence is recorded in the [P6 exit report](architecture-governance-completeness-exit-report.md).

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
| [P6 exit report](architecture-governance-completeness-exit-report.md) | immutable P6 scope/validation record | Complete |
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

P6 introduces no new architecture decision because consolidation does not change accepted runtime architecture. ADR lifecycle is explicit: Proposed, Accepted, Superseded, Rejected.

## 4. Canonical domain dependency coverage

The [cross-domain dependency map](cross-domain-dependency-map.md) represents exactly these 14 code domains once as consumers:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, `Recruitment`.

Coverage: **14/14**. Dependency notation is consumer → owning supported contract; P6 intentionally does not freeze raw import counts.

## 5. High-risk architecture boundary coverage

All frozen high-risk boundaries have current system-level visibility: global identity versus tenant authority, Platform admin versus Alliance roles, active tenant context, Content/Recruitment ownership, Events/Rallies/Notifications/Contributions collaboration, Audit versus outbox, producer event versus public webhook, platform Alliance versus game-side KingdomAlliance, neutral Kingdoms identity versus tenant observations, and repository hardening versus real-production approval.

## 6. Shared top-level ownership audit

P6 confirms product/security/operations remain genuinely shared/program-wide and current domain-specific behavior/evidence remains under `docs/domains/<domain>/`.

Result: **no additional domain-specific relocation is required** by P6.

## 7. Current-state navigation result

Current docs/product/ADR/capability/audit navigation is complete and links current architecture, dependency map, glossary, domain owners, shared security/operations, and production boundary.

## 8. Terminology result

The [shared glossary](glossary.md) covers all frozen high-risk architecture/product terms and status/release distinctions.

## 9. Historical and obsolete narrative result

Historical evidence remains preserved; current audits no longer carry migration-candidate status; no obsolete duplicate living architecture tree or accepted historical evidence deletion was required.

## 10. P6 architecture enforcement

`tests/Architecture/ArchitectureGovernanceDocumentationTest.php` protects required artifacts, ADR lifecycle/indexing, 14-domain dependency parity, glossary coverage, audit currency, shared ownership, and navigation. Existing architecture checks retain P1–P5 and local-link enforcement.

## 11. Candidate validation result

Exact candidate/evidence head `b2d63ffceea50658c989a569a44ad98fc47db75a` passed:

- Dependency Review `31518789039` — success;
- CodeQL `31518789038` — success;
- CI `31518789030` — success.

CI included **487 Pint files**, PHPStan/Larastan **345/345 with 0 errors**, **395 tests / 9,104 assertions**, frontend build, PostgreSQL migrations, P6/prior documentation checks, immutable image, staging, backup/restore, and image scan.

## 12. P6 exit checklist

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
- [x] P6 exit report created and content candidate `3bf6b7a7479e64739c1d650bcb02ccbfba25ffdf` recorded.
- [x] Exact P6 candidate/evidence head protected-green.
- [ ] P6 final evidence/status head protected-green.

P6 candidate acceptance is complete. P7 is selected only after the exact final P6 evidence/status head passes the second protected gate.
