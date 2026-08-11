# Testing, evidence, and traceability coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P5` — Testing, evidence, and traceability completeness  
**Inventory state:** Frozen — implementation/documentation normalization in progress

## 1. Purpose

This is the authoritative DCP-P5 inventory. It maps critical domain claims to executable validation classes and identifies the historical acceptance/evidence records that must retain sufficient immutable identity.

The governing rules are in [Testing, evidence, and traceability documentation standard](testing-evidence-standard.md).

P5 does not require one document per test file. It requires one living validation map per domain plus complete, immutable acceptance identity for historical gates.

## 2. Canonical executable validation baseline

### PHPUnit suites

`phpunit.xml` defines exactly six current suites:

| Suite | Directory | Evidence purpose |
| --- | --- | --- |
| `Architecture` | `tests/Architecture` | ownership, structure, forbidden boundaries, documentation guards, static accessibility/architecture invariants |
| `Feature` | `tests/Feature` | first-party HTTP/application behavior, permission, payload/disclosure, user workflow |
| `Integration` | `tests/Integration` | database/cross-domain/queue/outbox/integration/migration workflows |
| `Performance` | `tests/Performance` | explicit query-count/realistic-volume/bounded-work regression gates |
| `TenantIsolation` | `tests/TenantIsolation` | cross-Alliance object/reference/disclosure/mutation denial |
| `Unit` | `tests/Unit` | deterministic value-object/parser/calculation/state-machine logic |

### Backend command

`composer check` runs:

- Pint `--test`;
- PHPStan/Larastan; and
- ParaTest over the configured PHPUnit suites.

### Frontend command

`npm run check` runs:

- ESLint check;
- Prettier check;
- Vue/TypeScript type checking; and
- Vite production build.

### Protected workflows

Accepted protected gates use:

- Dependency Review;
- CodeQL; and
- CI.

The CI workflow additionally demonstrates PostgreSQL migrations, immutable production-image build, ephemeral staging, backup/restore and image vulnerability scan.

## 3. Frozen domain traceability inventory

The suite classes below identify **applicable evidence classes**, not a claim that every domain owns one physical test file in every listed directory.

| Domain | Critical claims requiring traceability | Primary evidence classes | Historical/accessibility evidence | Status |
| --- | --- | --- | --- | --- |
| Alliances | active tenant context, Alliance lifecycle/activation, no tenant fabrication, internal-only Kingdom event boundary | Architecture, Feature, Integration, TenantIsolation | Phase 1 exit/accessibility; P1–P4 DCP living contracts | In progress |
| Audit | attributable append evidence, safe metadata, separation from outbox transport | Architecture, Feature, Integration | Phase 1 exit; security/operations living profiles | In progress |
| Authorization | permission vocabulary, hierarchy, same-Alliance roles, last-Owner safety, Platform-admin separation | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 1 exit/accessibility; Phase 6 Platform evidence | In progress |
| Content | public/member/manager separation, publication/revision/media safety, scheduled publication, private storage boundary | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 2 exit/accessibility/migration evidence | In progress |
| Contributions | immutable correction/reversal, Events reconciliation, explainable calculations, report/export schema, schedule source state | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Phase 5 exit/accessibility/migration evidence | In progress |
| Events | recurrence/time zones, capacity/waitlist/attendance concurrency, reminder source facts, CSV/ICS disclosure | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Phase 3 exit/accessibility/migration evidence | In progress |
| Identity | authentication/recovery/session/MFA assurance, secret handling, account-deletion handoff | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 1 exit/accessibility/security evidence | In progress |
| Integrations | machine credential format/scopes/tenant binding, API bounds, webhook eligibility/signature/retry/idempotency | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Phase 6 exit/accessibility/security/operations evidence | In progress |
| Kingdoms | neutral identity, tenant-owned roster/snapshots/import/transfer/diplomacy, query budgets, no public API/webhook/automation | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | KINGDOMS-001/002/003 validation/accessibility/exit evidence | In progress |
| Memberships | invitation token lifecycle, acceptance concurrency, membership lifecycle, role adapters, last-Owner coordination | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 1 exit/accessibility/security evidence | In progress |
| Notifications | deterministic reminder/report materialization, outbox handoff, at-least-once consumer idempotency, source recheck | Architecture, Feature, Integration, Unit | Phase 3/5 accepted evidence; P3 operations evidence | In progress |
| Platform | Platform-admin separation, lifecycle/legal holds/export, outbox publication, runtime/launch controls, recovery gate | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Phase 6 exit/accessibility/DR; production hardening evidence | In progress |
| Rallies | formation composition, group capacity/assignment, participation, Events adapter ownership, tenant isolation | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 3 exit/accessibility evidence | In progress |
| Recruitment | public/private intake separation, invitation mode, candidate workflow/merge/onboarding, retention/anonymization | Architecture, Feature, Integration, TenantIsolation, Unit | Phase 4 exit/accessibility/migration evidence | In progress |

## 4. Architecture and documentation evidence classes

Current architecture coverage includes repository/domain ownership, domain-dependency direction, tenant/persistence boundaries, documentation structure, P1 domain contracts, P2 security profiles/reviews, P3 operations profiles/runbooks, P4 interface profiles/contracts and P5 testing/evidence structure.

P5 adds structural enforcement without attempting brittle parsing of every PHPUnit test name.

## 5. Tenant/security/privacy evidence classes

Critical tenant/security claims are expected to be discoverable through the owning domain testing profile and, where applicable:

- `TenantIsolation` suite;
- Feature/Integration permission and object-resolution tests;
- Architecture forbidden-boundary tests;
- living domain security profile/reviews; and
- accepted phase/increment threat/security evidence.

A living security document without executable regression evidence is not treated as proof of a repository-testable invariant.

## 6. Migration, rollback and recovery evidence

Current repository evidence classes are:

- every CI PHP job runs PostgreSQL forward migrations;
- accepted Phase 2–6/Kingdoms evidence includes domain/increment rollback/reapply tests where introduced;
- the CI container job demonstrates database backup/restore, release/image identity and post-restore readiness; and
- P3 operations profiles define recovery sets that may extend beyond PostgreSQL, such as Content private media and Identity key material.

P5 does not conflate schema rollback with disaster recovery or database restore with object-storage recovery.

## 7. Performance and capacity evidence

The `Performance` suite is applicable only to explicit bounded-work claims. Accepted examples include realistic-volume Kingdoms roster/transfer/alliance-intelligence query gates and bounded Phase 6 integration/capacity behavior.

Domain profiles must not invent SLAs/query budgets when no accepted executable threshold exists.

## 8. Accessibility and frontend evidence

Current frontend repository quality is `npm run check`; this is not by itself an accessibility certification.

Historical accepted accessibility evidence remains indexed at:

- `docs/product/phase-1-accessibility-review.md`;
- `docs/product/phase-2-accessibility.md`;
- `docs/product/phase-3-accessibility.md`;
- `docs/product/phase-4-accessibility.md`;
- `docs/product/phase-5-accessibility.md`;
- `docs/product/phase-6-accessibility.md`; and
- `docs/domains/kingdoms/product/README.md` for K1–K3 accessibility records.

Source-level accessibility guards remain executable regression evidence where implemented. Deployment-specific browser/device/screen-reader/branding checks remain external where the accepted records say so.

## 9. Historical Phase 0–6 acceptance identity audit

| Evidence record | Immutable SHA | Protected run identity | P5 decision |
| --- | --- | --- | --- |
| Phase 0 exit | `9b9e525cabac831ba62601e9847bf8e0168183c1` | DR `31142532395`, CodeQL `31142532453`, CI `31142532578` | Sufficient |
| Phase 1 exit | `ca3d5ad851ec88a0ef127817a3fbf670f7a0352c` | DR `31150029638`, CodeQL `31150029682`, CI `31150029637` | Sufficient |
| Phase 2 exit | implementation `3c137d74a608e57605256cd9e58b5a6cbee62a36`; acceptance `1f73da358c1e1507c2c070b22224d067e118033a` | implementation DR `31155725904`, CodeQL `31155726592`, CI `31155726752`; acceptance DR `31156084812`, CodeQL `31156084422`, CI `31156085482` | Sufficient |
| Phase 3 exit | `ad1cbf3228f86dd915dbc82466d441f7aca0c475` | DR `31187575970`, CodeQL `31187578967`, CI `31187575503` | Sufficient |
| Phase 4 exit | technical `27c6822593d7d54bddbc360dcea1a104ba5dadba`; acceptance `d35ba746f405a4b41c310a406c41ce5c27a70091` | technical DR `31205805866`, CodeQL `31205806726`, CI `31205805622`; acceptance DR `31206163527`, CodeQL `31206164215`, CI `31206163505` | Exit record technical identity sufficient; acceptance identity retained in PR evidence |
| Phase 5 exit | final PR head `c30aaab0ee3b03c65f27042a2700540bdebbf9c4` | DR `31219686800`, CodeQL `31219686802`, CI `31219686960` | **Required P5 hardening:** add recovered SHA/run IDs to exit report |
| Phase 6 exit | implementation `d1969889ffa044cd7690f263ba9ef70c63a425cb`; final PR head `35979623d8231ee56b8fbcb75301e7e0732df0ca` | implementation DR `31235514849`, CodeQL `31235514858`, CI `31235514843`; final DR `31252682835`, CodeQL `31252682836`, CI `31252682853` | **Required P5 hardening:** add recovered run IDs/final head to exit report |

P5 will not rewrite the historical acceptance scope; it adds recovered immutable identity only where the accepted record was weaker than the standard.

## 10. Kingdoms K1–K3 accepted evidence audit

Kingdoms domain-owned product evidence is indexed by `docs/domains/kingdoms/product/README.md` and already preserves accepted increment scope, slice validations, accessibility and exit records.

Immutable whole-increment gates include:

- `KINGDOMS-001` implementation `7f743507b70865692290f517cd2de494ec54abae` — DR `31288932532`, CodeQL `31288932537`, CI `31288932560`; final head `9e71427e081928d9a91d986048c03ee3116bff7c` — DR `31289567298`, CodeQL `31289567296`, CI `31289567297`.
- `KINGDOMS-002` implementation `64189559c66e15dc56ec31f9b340284c89c30e6c` — DR `31337595942`, CodeQL `31337595933`, CI `31337595937`.
- `KINGDOMS-003` implementation `068c4086744f71d33453734f1f1b05fe1430cbff` — DR `31430279647`, CodeQL `31430279652`, CI `31430279638`.

These remain historical evidence rather than current Kingdoms contract text.

## 11. DCP P1–P4 acceptance evidence

DCP exit/status records already distinguish content candidate, protected candidate and final evidence/status heads. P5 reuses those immutable acceptance records rather than copying their historical test counts into living domain profiles.

Accepted DCP final transition heads through P4 are:

- P1 `60357543256478aa8ef8c26f67e27631df8c5ba4`;
- P2 `35121bf732f75c72351a7c232548f3e78fb1c8ff`;
- P3 clean restored/validated transition `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171`; and
- P4 final evidence/status head `286847006544d1af2e4dbf2f0211c5f28ad2cb33`, protected by DR `31513724817`, CodeQL `31513724836`, and CI `31513724840`.

## 12. Required P5 living artifacts

P5 requires exactly one current testing/evidence profile for each canonical domain:

```text
docs/domains/<domain>/testing/README.md
```

No new focused per-test documentation is required by the frozen inventory. Independently deep accepted evidence remains in its existing phase/increment/security/operations/accessibility owner.

## 13. P5 structural enforcement target

`tests/Architecture/TestingEvidenceDocumentationTest.php` will enforce:

- 14/14 code-domain/testing-profile parity;
- required profile metadata and 12-section order;
- links to owning domain/security/operations/interfaces;
- all six exact PHPUnit suite names/directories represented in this matrix;
- `composer check` and `npm run check` represented;
- Dependency Review, CodeQL and CI evidence classes represented;
- Phase 0–6 exit/accessibility evidence represented;
- Kingdoms K1–K3 product evidence represented; and
- repository-wide Markdown-link integrity through the existing architecture gate.

## 14. P5 exit checklist

- [x] Testing/evidence documentation standard adopted.
- [x] Six-suite executable validation baseline inventoried.
- [x] Backend/frontend/protected workflow evidence classes inventoried.
- [x] 14-domain critical-claim traceability inventory frozen.
- [x] Phase 0–6 acceptance/accessibility evidence audited.
- [x] Kingdoms K1–K3 evidence audited.
- [x] DCP P1–P4 evidence audited.
- [ ] Phase 5 historical exit identity hardened with recovered SHA/run IDs.
- [ ] Phase 6 historical exit identity hardened with recovered implementation/final run IDs.
- [ ] 14/14 living domain testing/evidence profiles implemented.
- [ ] Domain/product navigation normalized.
- [ ] P5 architecture enforcement active.
- [ ] Complete link/traceability review passes.
- [ ] Exact P5 candidate head passes protected Dependency Review, CodeQL and complete CI.
- [ ] P5 final evidence/status head protected-green.

P5 is **In progress**. P6 remains blocked.
