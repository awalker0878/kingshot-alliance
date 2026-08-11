# Testing, evidence, and traceability coverage matrix

[← Documentation Completion Program](documentation-program-plan.md)

**Document type:** DCP phase coverage inventory  
**Status:** Current  
**Phase:** `DCP-P5` — Testing, evidence, and traceability completeness  
**Inventory state:** Frozen — content and candidate validation complete; final evidence-head validation pending

## 1. Purpose

This is the authoritative DCP-P5 inventory. It maps critical domain claims to executable validation classes and immutable historical acceptance/evidence identity. The governing rules are in [Testing, evidence, and traceability documentation standard](testing-evidence-standard.md), with candidate acceptance recorded in [P5 exit report](testing-evidence-completeness-exit-report.md).

## 2. Canonical executable validation baseline

`phpunit.xml` defines exactly six suites:

| Suite | Directory | Evidence purpose |
| --- | --- | --- |
| `Architecture` | `tests/Architecture` | ownership, structure, forbidden boundaries, documentation guards |
| `Feature` | `tests/Feature` | first-party HTTP/application behavior, authorization, disclosure, workflows |
| `Integration` | `tests/Integration` | database, cross-domain, queue/outbox, migration/integration workflows |
| `Performance` | `tests/Performance` | explicit query-count/realistic-volume/bounded-work gates |
| `TenantIsolation` | `tests/TenantIsolation` | cross-Alliance object/reference/disclosure/mutation denial |
| `Unit` | `tests/Unit` | deterministic value/parser/calculation/state-machine behavior |

`composer check` runs Pint test mode, PHPStan/Larastan, and ParaTest. `npm run check` runs ESLint, Prettier, Vue/TypeScript type checking, and Vite production build.

Protected evidence classes are Dependency Review, CodeQL, and CI. CI also demonstrates PostgreSQL migrations, immutable production-image build, ephemeral staging, backup/restore, and image vulnerability scan.

## 3. Frozen domain traceability inventory

| Domain | Critical claims | Primary evidence classes | Status |
| --- | --- | --- | --- |
| Alliances | active tenant context, lifecycle/activation, no tenant fabrication | Architecture, Feature, Integration, TenantIsolation | Complete |
| Audit | attributable append evidence, safe metadata, transport separation | Architecture, Feature, Integration | Complete |
| Authorization | permissions, hierarchy, same-Alliance roles, last-Owner, Platform separation | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |
| Content | public/member/manager separation, publication/revisions/media/scheduler | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |
| Contributions | immutable history, reconciliation, calculations, exports/schedules | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Complete |
| Events | recurrence/time zones, registration concurrency, disclosure, CSV/ICS | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Complete |
| Identity | auth/recovery/session/MFA, secrets, deletion handoff | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |
| Integrations | machine credentials/scopes, API bounds, webhook signature/retry/idempotency | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Complete |
| Kingdoms | neutral identity, tenant state, imports/transfers/diplomacy, query gates, no public automation | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Complete |
| Memberships | invitations, acceptance concurrency, lifecycle, role adapters | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |
| Notifications | deterministic materialization, outbox handoff/consumer, source recheck | Architecture, Feature, Integration, Unit | Complete |
| Platform | admin separation, lifecycle/legal hold/export/outbox/runtime/recovery | Architecture, Feature, Integration, Performance, TenantIsolation, Unit | Complete |
| Rallies | composition, groups/assignments/participation, Event adapters, isolation | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |
| Recruitment | public/private intake, invitation mode, pipeline/onboarding/retention | Architecture, Feature, Integration, TenantIsolation, Unit | Complete |

All 14 domains have a current `docs/domains/<domain>/testing/README.md` profile.

## 4. Security, tenancy, interfaces, and asynchronous evidence

Living profiles map repository-testable security/privacy/tenant/interface claims to Architecture, Feature, Integration, TenantIsolation, Unit, and Performance evidence as applicable. They distinguish producer-domain meaning, Platform outbox publication, internal consumers, and Integrations external delivery.

Material idempotency/concurrency evidence includes membership invitation/role repeat behavior, Event capacity/waitlist concurrency, Contributions corrections/reconciliation, deterministic Notifications identities, at-least-once outbox consumers, webhook delivery identity/backoff, Kingdoms snapshot/import/transfer idempotency, and Recruitment onboarding/retention reconciliation.

## 5. Migration, rollback, recovery, and performance evidence

P5 distinguishes clean PostgreSQL forward migration, accepted domain/increment rollback/reapply evidence, and CI database backup/restore/release/staging evidence. Database restore is not treated as proof of Content media recovery, Identity key recovery, or reversal of external side effects.

Performance claims exist only where executable accepted evidence exists. Kingdoms retains realistic-volume query gates, including K3's `<= 10` SELECT manager-intelligence bound. Domains without accepted numeric thresholds do not invent SLAs/query budgets.

## 6. Accessibility and frontend evidence

`npm run check` is frontend quality evidence, not accessibility certification. Historical accepted accessibility evidence remains:

- `docs/product/phase-1-accessibility-review.md`;
- `docs/product/phase-2-accessibility.md`;
- `docs/product/phase-3-accessibility.md`;
- `docs/product/phase-4-accessibility.md`;
- `docs/product/phase-5-accessibility.md`;
- `docs/product/phase-6-accessibility.md`; and
- `docs/domains/kingdoms/product/README.md` for K1–K3 accessibility/acceptance evidence.

## 7. Historical Phase 0–6 acceptance identity audit

| Evidence record | Immutable identity | Protected runs | Result |
| --- | --- | --- | --- |
| Phase 0 exit | `9b9e525cabac831ba62601e9847bf8e0168183c1` | DR `31142532395`, CodeQL `31142532453`, CI `31142532578` | Sufficient |
| Phase 1 exit | `ca3d5ad851ec88a0ef127817a3fbf670f7a0352c` | DR `31150029638`, CodeQL `31150029682`, CI `31150029637` | Sufficient |
| Phase 2 exit | implementation `3c137d74a608e57605256cd9e58b5a6cbee62a36`; acceptance `1f73da358c1e1507c2c070b22224d067e118033a` | implementation DR `31155725904`, CodeQL `31155726592`, CI `31155726752`; acceptance DR `31156084812`, CodeQL `31156084422`, CI `31156085482` | Sufficient |
| Phase 3 exit | `ad1cbf3228f86dd915dbc82466d441f7aca0c475` | DR `31187575970`, CodeQL `31187578967`, CI `31187575503` | Sufficient |
| Phase 4 exit | technical `27c6822593d7d54bddbc360dcea1a104ba5dadba`; acceptance `d35ba746f405a4b41c310a406c41ce5c27a70091` | technical DR `31205805866`, CodeQL `31205806726`, CI `31205805622`; acceptance DR `31206163527`, CodeQL `31206164215`, CI `31206163505` | Sufficient |
| Phase 5 exit | `c30aaab0ee3b03c65f27042a2700540bdebbf9c4` | DR `31219686800`, CodeQL `31219686802`, CI `31219686960` | Hardened in P5 |
| Phase 6 exit | implementation `d1969889ffa044cd7690f263ba9ef70c63a425cb`; final `35979623d8231ee56b8fbcb75301e7e0732df0ca` | implementation DR `31235514849`, CodeQL `31235514858`, CI `31235514843`; final DR `31252682835`, CodeQL `31252682836`, CI `31252682853` | Hardened in P5 |

Historical hardening preserved original accepted scope and test counts.

## 8. Kingdoms K1–K3 evidence

`docs/domains/kingdoms/product/README.md` indexes the full domain-owned evidence. Whole-increment gates include:

- K1 implementation `7f743507b70865692290f517cd2de494ec54abae` — DR `31288932532`, CodeQL `31288932537`, CI `31288932560`; final `9e71427e081928d9a91d986048c03ee3116bff7c` — DR `31289567298`, CodeQL `31289567296`, CI `31289567297`.
- K2 `64189559c66e15dc56ec31f9b340284c89c30e6c` — DR `31337595942`, CodeQL `31337595933`, CI `31337595937`.
- K3 `068c4086744f71d33453734f1f1b05fe1430cbff` — DR `31430279647`, CodeQL `31430279652`, CI `31430279638`.

## 9. DCP P1–P4 evidence

Accepted final transition heads through P4 are:

- P1 `60357543256478aa8ef8c26f67e27631df8c5ba4`;
- P2 `35121bf732f75c72351a7c232548f3e78fb1c8ff`;
- P3 `986cb6e0c2cb0cb6d5b84fe6fafdd1159e899171`; and
- P4 `286847006544d1af2e4dbf2f0211c5f28ad2cb33` — DR `31513724817`, CodeQL `31513724836`, CI `31513724840`.

## 10. P5 structural enforcement

`tests/Architecture/TestingEvidenceDocumentationTest.php` enforces:

- 14/14 testing-profile parity, metadata, section order, and canonical links;
- exact six-suite names/directories from `phpunit.xml`;
- `composer check`, `npm run check`, Dependency Review, CodeQL, CI, PostgreSQL migrations, immutable production-image build, ephemeral staging, backup/restore, and image vulnerability scan evidence classes;
- Phase 0–6 exit/accessibility evidence;
- Kingdoms product evidence; and
- retained recovered Phase 5/6 SHA/run identities.

Existing architecture tests continue repository-wide Markdown-link integrity and prior DCP documentation gates.

## 11. Protected P5 candidate evidence

Candidate/evidence head `221d8bda2d68a8ffe72ca00845d53656b7e0ab32` passed:

- Dependency Review `31515787801` — success;
- CodeQL `31515787822` — success;
- CI `31515787790` — success.

The complete CI chain passed frontend, PostgreSQL migrations, PHP formatting/static analysis/full PHPUnit execution, P5 architecture/traceability checks, repository Markdown links, immutable image, staging, backup/restore, and image scan.

## 12. Exit checklist

- [x] Testing/evidence standard adopted.
- [x] Six-suite executable validation baseline inventoried.
- [x] Backend/frontend/protected evidence classes inventoried.
- [x] 14-domain traceability inventory frozen.
- [x] Phase 0–6 acceptance/accessibility evidence audited.
- [x] Kingdoms K1–K3 evidence audited.
- [x] DCP P1–P4 evidence audited.
- [x] Phase 5 and Phase 6 immutable identity hardened.
- [x] 14/14 living domain testing/evidence profiles implemented.
- [x] Domain/product navigation normalized.
- [x] P5 architecture enforcement active.
- [x] Exact P5 candidate/evidence head passed protected Dependency Review, CodeQL, and complete CI.
- [ ] Exact final P5 exit/status evidence head protected-green.

P5 content and candidate validation are complete. P6 remains blocked until the final evidence/status head passes the same protected gate.
