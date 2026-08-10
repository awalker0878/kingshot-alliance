# Repository documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Applies to:** `docs/`, canonical `app/Domain/*/README.md` files, and documentation-related architecture tests  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This document defines repository documentation ownership, structure, naming, minimum contract coverage, and CI enforcement.

The governing principle is simple:

> Documentation about a code/domain's business ownership, implementation contract, domain-specific product evidence, domain-specific security, and domain-specific operations belongs with that domain. Top-level documentation areas describe the overall program, shared runtime, or cross-domain policy.

A contributor who sees `app/Domain/Events` can derive its canonical living contract without repository search:

```text
app/Domain/Events/
        ↕
docs/domains/events/README.md
```

Code and tests remain authoritative for exact implemented runtime behavior. Documentation records intended ownership, stable business contracts, product/status evidence, security/operating boundaries, and accountable program decisions.

## 2. Five top-level documentation groups

The repository has exactly five top-level documentation groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Do not create parallel top-level groups such as `docs/architecture/`, `docs/runbooks/`, `docs/wiki/`, `docs/features/`, or `docs/reference/`.

### `docs/adr/`

Owns material architecture decisions and rationale that cross individual feature files or must survive implementation details.

### `docs/domains/`

Owns current code/business-domain contracts and domain-specific evidence/operations/security.

### `docs/product/`

Owns repository/program-wide product governance:

- baseline implementation plan;
- current capability/status navigation;
- documentation/architecture governance;
- cross-domain/program audits;
- historical phase-wide acceptance/accessibility evidence; and
- production hardening/launch approval state.

It does **not** act as a flat storage area for implementation plans, validations, security reviews, or exit reports that belong primarily to one code domain.

### `docs/security/`

Owns repository-wide security policy and program evidence:

- shared security baseline;
- historical phase-wide threat models; and
- production-launch security evidence.

Domain-specific security reviews belong under the owning domain.

### `docs/operations/`

Owns the shared runtime operating platform:

- configuration;
- scheduler/queues/outbox;
- observability;
- deployment/release controls;
- backup/restore/rollback/incident runbooks; and
- historical phase-wide operating evidence.

Domain-specific diagnostics/operating contracts belong under the owning domain and consume these shared runbooks.

Every top-level group has `README.md` navigation.

## 3. Canonical domain structure

The canonical code roots are:

```text
app/Domain/
  Alliances/
  Audit/
  Authorization/
  Content/
  Contributions/
  Events/
  Identity/
  Integrations/
  Kingdoms/
  Memberships/
  Notifications/
  Platform/
  Rallies/
  Recruitment/
```

Documentation mirrors that set exactly:

```text
docs/domains/
  README.md
  alliances/README.md
  audit/README.md
  authorization/README.md
  content/README.md
  contributions/README.md
  events/README.md
  identity/README.md
  integrations/README.md
  kingdoms/README.md
  memberships/README.md
  notifications/README.md
  platform/README.md
  rallies/README.md
  recruitment/README.md
```

For every canonical code domain:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
```

Both files are required.

- The code-local README is concise developer navigation.
- The docs-domain README is the canonical living business/runtime contract.
- `docs/domains/README.md` is the only Markdown file permitted directly under `docs/domains/`.
- The first-level domain-directory set is bidirectionally identical to `app/Domain/*` after canonical kebab-case normalization.

## 4. Domain-owned documentation

A domain folder may contain four kinds of material beneath its canonical README.

### 4.1 Capability contracts

```text
docs/domains/<domain>/<capability>.md
```

Create a capability document when the capability has a distinct lifecycle/invariants, authorization/privacy boundary, substantial persistence/query semantics, significant integration/import/export behavior, or enough complexity that the root README becomes difficult to navigate.

Do not create one file per model, controller, route, table, action, query, enum, or value object.

### 4.2 Product and acceptance evidence

```text
docs/domains/<domain>/product/
  README.md
  <scope-or-evidence>.md
```

Use this for domain-specific:

- product increment scopes;
- implementation plans;
- design decision records associated with a named increment;
- slice validation records;
- accessibility records; and
- increment exit/acceptance reports.

Historical evidence filenames may retain increment/slice identifiers because those identifiers are part of the record identity.

### 4.3 Security evidence

```text
docs/domains/<domain>/security/
  README.md
  <subject>-security-review.md
```

Use this for domain-specific security/privacy reviews. Shared baseline and production-launch security remain top-level `docs/security/`.

### 4.4 Operations

```text
docs/domains/<domain>/operations/
  README.md
  <capability>.md
```

Use this for domain-specific persisted-state semantics, diagnostics, replay/idempotency, data-quality interpretation, query/performance constraints, and safe operational recovery.

Shared deployment/configuration/observability/runbooks remain top-level `docs/operations/` and are linked rather than copied.

## 5. Current Kingdoms example

The accepted Kingdoms documentation demonstrates the model:

```text
docs/domains/kingdoms/
  README.md
  roster.md
  snapshots.md
  intelligence.md
  csv-migration.md
  transfer-planning.md
  alliance-intelligence.md
  product/
    README.md
    kingdoms-roster-intelligence-*.md
    kingdoms-transfer-planning-*.md
    kingdoms-alliance-intelligence-*.md
  security/
    README.md
    kingdoms-*-security-review.md
  operations/
    README.md
    kingdoms-roster-intelligence.md
    kingdoms-transfer-planning.md
    kingdoms-alliance-intelligence.md
```

Top-level product/security/operations indexes point to these domain-owned areas instead of duplicating their file inventories.

## 6. Naming rules

- Markdown filenames use lowercase kebab-case.
- Directory indexes are `README.md`.
- Domain directory names are lowercase kebab-case forms of canonical code-domain names.
- Capability filenames use `<capability>.md`; do not repeat the domain name merely because the old flat path did.
- Living filenames do not encode temporary PR/phase/slice names.
- Historical evidence may retain phase/increment/slice identifiers.
- Do not use vague names such as `notes.md`, `misc.md`, `new-plan.md`, `design2.md`, `final.md`, or `overview-new.md`.
- Dates belong in evidence metadata rather than living filenames.
- Moves/renames update repository-relative links in the same change.
- Accepted historical evidence is not cosmetically renamed when relocation alone satisfies ownership.

## 7. Code-local README format

Every `app/Domain/<Domain>/README.md` uses:

```markdown
# <Domain> domain

## Purpose

## Owned code

## Public contracts

## Dependencies

## Canonical documentation

- [`docs/domains/<domain>/`](../../../docs/domains/<domain>/README.md)
```

Rules:

- keep it concise;
- describe module ownership/public contracts/dependencies, not full user lifecycle/security/runbook detail;
- do not use historical phase narrative as the primary description; and
- always link the canonical docs-domain root.

## 8. Canonical domain README format

Every `docs/domains/<domain>/README.md` uses this section order:

1. Purpose and ownership.
2. Scope (in/out).
3. Domain model.
4. Core invariants.
5. Lifecycles and workflows.
6. Authorization and tenancy.
7. Cross-domain contracts (consumes/exposes).
8. Persistence and data ownership.
9. Events, outbox and integrations.
10. HTTP, UI and API surfaces.
11. Background processing.
12. Failure, idempotency and concurrency.
13. Security and privacy.
14. Observability and operations.
15. Testing and architecture enforcement.
16. Explicit non-capabilities.
17. Capability/evidence/operations documents.
18. Related documentation.

Required metadata:

```markdown
**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/<Domain>`  
**Primary authorization boundary:** `<permission/policy/platform grant or N/A>`
```

Describe stable business/runtime rules rather than line-by-line implementation. State tenant/global, missing/zero, active/archived, and current/historical semantics explicitly where relevant.

## 9. Capability document format

A capability document uses:

1. Purpose.
2. Scope and non-scope.
3. Model and state.
4. Invariants.
5. Workflows.
6. Authorization, tenancy and privacy.
7. Persistence and query semantics.
8. Events/integrations/background processing.
9. Failure, idempotency and concurrency.
10. Operations and observability.
11. Tests and validation.
12. Related documentation.

Required metadata:

```markdown
**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** `<Domain>`
```

Capability documents deepen the root domain contract; they do not redefine ownership.

## 10. Evidence formats

### Product increment scope

Record stable scope ID, status, owner, dependencies, outcome/problem, approved scope, explicit non-scope, domain ownership, authorization/tenancy/privacy, data/integration boundaries, security/operations/accessibility requirements, acceptance criteria, and deferred follow-ons.

### Implementation plan

Record dependency baseline, locked decisions, ordered implementation slices, per-slice scope/non-scope, verification gates, migration/rollback requirements, and whole-increment acceptance gate.

### Validation record

Record exact validated SHA/date plus scope, runtime behavior, tests/checks, security/tenant/privacy assertions, migration/rollback, performance/query evidence where relevant, and remaining scope.

### Exit report

Record Accepted status, exact validated implementation SHA, final evidence/status SHA where different, accepted capability, whole-increment invariants, security/privacy/accessibility/migration/performance/operations/integration results, protected evidence IDs, deferred scope, and production-cutover boundary.

### Security review

Record scope/assets, trust boundaries, attackers/abuse cases, authorization/tenant/privacy/integrity/integration threats, controls, verification, residual risk, and external evidence requirements.

### Operations guide

Record purpose/scope, runtime state, configuration dependencies, normal flow, observability, failure diagnosis, recovery/replay/idempotency, migration/rollback, capacity/performance, security/secret handling, and evidence to retain.

### Runbook

Record purpose, preconditions, safety/stop conditions, procedure, validation, rollback/recovery, escalation, and evidence to retain.

## 11. Source-of-truth precedence

When documentation overlaps:

1. `docs/product/implementation-plan.md` for the approved baseline repository/program architecture.
2. Accepted ADRs for material architecture decisions/rationale.
3. Approved domain-owned product scopes for authorized post-baseline domain capability.
4. `docs/product/current-capability-matrix.md` and current approval/status records for present capability/go-no-go navigation.
5. `docs/domains/<domain>/README.md` and capability docs for current business/runtime contracts.
6. Shared/domain operations and security docs for operating/security requirements.
7. Validation, accessibility, exit, audit, and historical phase evidence for evidence.
8. Code/tests for exact implemented behavior.

A lower-precedence document must not silently redefine a higher-precedence contract. Documentation drift is a defect to reconcile, not a compatibility state to preserve.

## 12. Migration state

The former flat `docs/domains/*.md` layout has been removed. Combined guides were split by code ownership, Kingdoms capabilities moved beneath `docs/domains/kingdoms/`, and cross-domain architecture audits moved to program product evidence.

Domain-specific Kingdoms product, security, and operations material has also been moved out of the top-level program/shared directories into:

```text
docs/domains/kingdoms/product/
docs/domains/kingdoms/security/
docs/domains/kingdoms/operations/
```

No redirect/stub compatibility files are retained after repository links migrate.

## 13. CI enforcement

`tests/Architecture/RepositoryStructureTest.php` is the documentation-structure/link gate. It protects:

- exactly five top-level docs groups;
- required group indexes;
- bidirectional code-domain/docs-domain parity;
- required domain READMEs;
- no flat Markdown under `docs/domains/` except `README.md`;
- predictable kebab-case filenames;
- local Markdown link integrity;
- path-like Markdown references;
- canonical test roots; and
- repository-specific ownership regressions added as documentation architecture matures.

The gate should also prevent domain-specific files that have been deliberately migrated from silently reappearing in top-level program/shared directories.

## 14. Definition of documentation done

A material change is not documentation-complete until:

- the owning domain is identifiable;
- its canonical README remains correct;
- affected capability docs are updated;
- the code-local README remains correct when ownership/public contracts/dependencies change;
- domain-specific product/security/operations evidence is stored with that domain;
- shared program/security/operations docs are updated only when a cross-domain/shared rule changes;
- authorization, tenancy, privacy, idempotency, failure and integration behavior are documented where applicable;
- indexes/current capability navigation are updated;
- repository-relative links resolve; and
- protected documentation architecture checks pass on the exact final head.

The end state is deterministic: **code ownership determines documentation ownership; top-level documentation describes the overall program/shared platform.**
