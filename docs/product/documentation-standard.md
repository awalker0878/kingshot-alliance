# Repository documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Proposed  
**Applies to:** `docs/`, canonical `app/Domain/*/README.md` files, and documentation-related architecture tests  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This document defines the required structure, naming, ownership, minimum coverage, and standard format for repository documentation.

The goal is deterministic documentation. A developer who sees `app/Domain/Events` must be able to derive the documentation root without searching:

```text
app/Domain/Events/
        ↓
docs/domains/events/README.md
```

Every canonical code domain has one matching documentation directory. Domain capabilities live below that directory instead of being flattened into `docs/domains/` with repeated domain prefixes.

Code and tests remain authoritative for exact runtime behavior. Documentation defines intended ownership, business contracts, operating expectations, security boundaries, product status, and acceptance evidence and must remain aligned with implementation.

## 2. Canonical documentation topology

The repository keeps the five approved top-level documentation groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Do not create parallel top-level documentation groups such as `docs/architecture/`, `docs/runbooks/`, `docs/wiki/`, `docs/features/`, or `docs/reference/`.

### 2.1 Group ownership

| Group | Owns | Does not own |
| --- | --- | --- |
| `adr/` | Durable architecture decisions and rationale | Feature instructions, product status, operating procedures |
| `domains/` | Current domain contracts mapped to `app/Domain/*` | Product approval, historical phase evidence, runbooks |
| `operations/` | Runtime configuration, observability, deployment, recovery, runbooks | Product scope and business ownership |
| `product/` | Baseline plan, named increments, capability/status records, acceptance evidence, documentation governance | Detailed runtime contracts owned by a domain |
| `security/` | Security baseline, threat models, security reviews, launch-security evidence | General business workflow documentation |

### 2.2 Code-local versus repository documentation

Every canonical code domain owns two documentation surfaces:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
```

The code-local README is concise developer navigation. The `/docs` directory is the authoritative living documentation surface for the domain.

The code-local README must link to the matching `/docs` directory and must not duplicate the full business contract.

## 3. Canonical domain directory structure

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

The matching documentation roots are exactly:

```text
docs/domains/
  README.md
  alliances/
    README.md
  audit/
    README.md
  authorization/
    README.md
  content/
    README.md
  contributions/
    README.md
  events/
    README.md
  identity/
    README.md
  integrations/
    README.md
  kingdoms/
    README.md
  memberships/
    README.md
  notifications/
    README.md
  platform/
    README.md
  rallies/
    README.md
  recruitment/
    README.md
```

The mapping is one-to-one and case-normalized:

| Code domain | Documentation root |
| --- | --- |
| `Alliances` | `docs/domains/alliances/` |
| `Audit` | `docs/domains/audit/` |
| `Authorization` | `docs/domains/authorization/` |
| `Content` | `docs/domains/content/` |
| `Contributions` | `docs/domains/contributions/` |
| `Events` | `docs/domains/events/` |
| `Identity` | `docs/domains/identity/` |
| `Integrations` | `docs/domains/integrations/` |
| `Kingdoms` | `docs/domains/kingdoms/` |
| `Memberships` | `docs/domains/memberships/` |
| `Notifications` | `docs/domains/notifications/` |
| `Platform` | `docs/domains/platform/` |
| `Rallies` | `docs/domains/rallies/` |
| `Recruitment` | `docs/domains/recruitment/` |

A new canonical code domain is incomplete until its matching documentation directory and `README.md` are added in the same change.

A documentation domain directory without a matching `app/Domain/<Domain>` root is also invalid unless the implementation plan or an accepted ADR explicitly changes the canonical domain set.

## 4. Domain capability files

A domain directory may contain capability documents when the root contract becomes too broad.

Use:

```text
docs/domains/<domain>/README.md
docs/domains/<domain>/<capability>.md
```

Examples:

```text
docs/domains/kingdoms/README.md
docs/domains/kingdoms/roster.md
docs/domains/kingdoms/snapshots.md
docs/domains/kingdoms/intelligence.md
docs/domains/kingdoms/csv-migration.md
docs/domains/kingdoms/transfer-planning.md
docs/domains/kingdoms/alliance-intelligence.md

docs/domains/events/README.md
docs/domains/events/recurrence.md
docs/domains/events/registration.md
docs/domains/events/attendance.md

docs/domains/rallies/README.md
docs/domains/rallies/formations.md
docs/domains/rallies/coordination.md
```

Because the folder already establishes ownership, capability filenames do **not** repeat the domain name.

Create a separate capability document when at least one is true:

- it has a distinct lifecycle with meaningful invariants;
- it has a distinct authorization or privacy boundary;
- it has substantial persistence/query semantics;
- it owns a significant import/export/integration contract;
- the domain README would otherwise become difficult to navigate; or
- it needs a stable review/operational link independent of the root contract.

Do not create one Markdown file per model, controller, route, table, action, query, enum, or value object.

## 5. Universal naming rules

Unless a more specific rule applies:

- Markdown filenames use lowercase kebab-case.
- Directory indexes are always `README.md`.
- Domain directory names are the lowercase kebab-case form of the canonical code domain.
- Capability files use `<capability>.md` inside the owning domain folder.
- Do not encode temporary implementation phase/slice names into living domain filenames.
- Do not use vague names such as `notes.md`, `misc.md`, `new-plan.md`, `design2.md`, `final.md`, or `overview-new.md`.
- Do not add dates to living filenames; dates belong in evidence metadata.
- Historical/evidence filenames may retain phase, increment, slice, or acceptance identifiers because those identifiers are part of the record identity.
- Renames must update repository-relative links in the same change.
- Accepted historical evidence is not renamed merely for cosmetic consistency.

## 6. File taxonomy outside domain folders

### 6.1 `docs/adr/`

```text
README.md
adr-template.md
NNNN-<decision-name>.md
```

Example: `0008-domain-first-source-layout.md`.

A material architecture decision receives a new numbered ADR. Do not silently rewrite historical rationale.

### 6.2 `docs/operations/`

Global living documents use stable descriptive names:

```text
configuration-reference.md
background-processing.md
observability.md
release-checklist.md
production-launch-runbook.md
branch-protection.md
```

Domain/capability operating guides use:

```text
<domain>-operations.md
<domain>-<capability>-operations.md
```

Runbooks use:

```text
runbooks/<procedure>.md
```

Historical phase evidence may retain `phase-<n>-...` naming.

### 6.3 `docs/product/`

Global/current records use stable names:

```text
implementation-plan.md
current-capability-matrix.md
definition-of-done.md
production-launch-approval.md
production-hardening-exit-report.md
documentation-standard.md
```

Named product increments use one stable slug:

```text
<increment-slug>-increment.md
<increment-slug>-implementation-plan.md
<increment-slug>-p0-decisions.md
<increment-slug>-slice-<id>-validation.md
<increment-slug>-accessibility.md
<increment-slug>-exit-report.md
```

Historical phase records may retain `phase-<n>-...` naming.

### 6.4 `docs/security/`

```text
security-baseline.md
<subject>-security-review.md
<subject>-p0-security-review.md
phase-<n>-threat-model.md
production-launch-security-review.md
```

Security filenames identify the protected subject, not the author or reviewing team.

## 7. Standard code-local domain README format

Every `app/Domain/<Domain>/README.md` follows this section order:

```markdown
# <Domain> domain

## Purpose

One short paragraph stating what the code module owns.

## Owned code

- `Actions/` — ...
- `Queries/` — ...
- `Models/` — ...
- `Services/` — ...
- `Http/` — ...
- `Enums/` / `ValueObjects/` — ...

List only directories/types that actually exist or matter architecturally.

## Public contracts

List intentional cross-domain actions, queries, services, value objects, enums, or events.

## Dependencies

List intentional cross-domain dependencies and why they exist. Do not inventory framework/library dependencies.

## Canonical documentation

- [`docs/domains/<domain>/`](../../../docs/domains/<domain>/README.md)
```

Rules:

- normally 30–100 lines;
- no full lifecycle/security/operations duplication;
- no historical phase narrative as the primary description;
- always link the matching `docs/domains/<domain>/README.md`;
- update `Owned code` when the internal module layout materially changes.

## 8. Standard canonical domain README format

Every final `docs/domains/<domain>/README.md` follows this section order. A section may say `Not applicable` with a short reason; relevant contract areas must not silently disappear.

```markdown
# <Domain> domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/<Domain>`  
**Primary authorization boundary:** `<permission/policy/platform grant or N/A>`

## 1. Purpose and ownership

## 2. Scope

### In scope

### Out of scope

## 3. Domain model

## 4. Core invariants

## 5. Lifecycles and workflows

## 6. Authorization and tenancy

## 7. Cross-domain contracts

### Consumes

### Exposes

## 8. Persistence and data ownership

## 9. Events, outbox and integrations

## 10. HTTP, UI and API surfaces

## 11. Background processing

## 12. Failure, idempotency and concurrency

## 13. Security and privacy

## 14. Observability and operations

## 15. Testing and architecture enforcement

## 16. Explicit non-capabilities

## 17. Capability documents

## 18. Related documentation
```

### Writing rules

- describe stable business rules and invariants, not line-by-line implementation;
- name important classes only when they are architectural entry points;
- do not maintain fragile exhaustive class/file counts;
- state tenant/global, missing/zero, active/archived, and current/historical semantics explicitly where relevant;
- state authorization in policy/permission terms, not merely UI role names;
- link deep capability details instead of duplicating them;
- link security and operations evidence instead of embedding full threat/runbook content.

## 9. Standard capability document format

A capability file `docs/domains/<domain>/<capability>.md` uses:

```markdown
# <Capability>

[← <Domain> domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** `<Domain>`

## 1. Purpose
## 2. Scope and non-scope
## 3. Model and state
## 4. Invariants
## 5. Workflows
## 6. Authorization, tenancy and privacy
## 7. Persistence and query semantics
## 8. Events/integrations/background processing
## 9. Failure, idempotency and concurrency
## 10. Operations and observability
## 11. Tests and validation
## 12. Related documentation
```

Capability documents deepen the root domain contract; they never redefine ownership established by `README.md`.

## 10. Standard product/evidence formats

### 10.1 Product increment scope

Required metadata:

```text
Document type: Product increment scope
Scope ID: <stable ID>
Status: Proposed | Approved | In progress | Accepted
Owner: <accountable owner/role>
Dependencies: <accepted scopes>
```

Required sections:

1. Outcome/problem.
2. Approved scope.
3. Explicit non-scope.
4. Domain ownership.
5. Authorization/tenancy/privacy.
6. Data/integration boundaries.
7. Security/operations/accessibility requirements.
8. Acceptance criteria.
9. Deferred follow-ons.

### 10.2 Implementation plan

Required sections:

1. Purpose and dependency baseline.
2. Locked cross-cutting decisions (`P0` where used).
3. Ordered implementation slices.
4. Per-slice scope/non-scope.
5. Verification gates.
6. Migration/rollback requirements.
7. Whole-increment acceptance gate.

### 10.3 Validation record

Required metadata:

```text
Document type: Validation evidence
Status: Validated
Validated implementation SHA: <exact SHA>
Validation date: <date>
```

Required sections:

1. Scope validated.
2. Runtime behavior.
3. Tests and checks.
4. Security/tenant/privacy assertions.
5. Migration/rollback evidence.
6. Performance/query evidence when relevant.
7. Explicit remaining scope.

### 10.4 Exit report

Required metadata:

```text
Document type: Acceptance evidence
Status: Accepted
Validated implementation SHA: <exact SHA>
Final evidence/status SHA: <exact SHA when different>
```

Required sections:

1. Accepted capability.
2. Whole-increment invariants.
3. Security/privacy review result.
4. Accessibility result.
5. Migration/rollback result.
6. Performance/query result.
7. Operations/integration result.
8. Protected checks/evidence IDs.
9. Deferred/unapproved scope.
10. Production-cutover boundary.

### 10.5 Security review

Required sections:

1. Scope/assets.
2. Trust boundaries.
3. Attackers and abuse cases.
4. Authorization/tenant threats.
5. Privacy/data exposure threats.
6. Integrity/idempotency/history threats.
7. Integration/egress/event threats.
8. Controls.
9. Verification.
10. Residual risk.
11. External evidence requirements.

### 10.6 Operations guide

Required sections:

1. Purpose/scope.
2. Runtime components/state.
3. Configuration.
4. Normal operating flow.
5. Observability/signals.
6. Failure diagnosis.
7. Recovery/replay/idempotency.
8. Migration/rollback.
9. Capacity/performance constraints.
10. Security/secret handling.
11. Evidence to retain.

### 10.7 Runbook

Required sections:

1. Purpose.
2. Preconditions.
3. Safety and stop conditions.
4. Procedure.
5. Validation.
6. Rollback/recovery.
7. Escalation.
8. Evidence to retain.

## 11. Source-of-truth precedence

When documents overlap, use this precedence:

1. `docs/product/implementation-plan.md` for the approved baseline repository/program architecture.
2. Accepted ADRs for architecture decisions and rationale.
3. Approved named product increment scopes for authorized post-baseline product scope.
4. `docs/product/current-capability-matrix.md` for present capability/status navigation.
5. `docs/domains/<domain>/README.md` plus capability documents for current business/runtime contracts.
6. Living operations/security documents for operating/security requirements.
7. Validation, exit, accessibility, audit, and historical phase records as evidence.
8. Code and tests for exact implemented behavior.

A lower-precedence document must not silently redefine a higher-precedence contract. If code/tests and documentation disagree, treat the discrepancy as a defect and reconcile it explicitly.

## 12. Migration from the current flat domain layout

Existing flat domain guides remain migration sources until their content is moved into the matching domain directory.

Examples:

```text
docs/domains/identity-tenancy-and-membership.md
    → docs/domains/identity/README.md
    → docs/domains/alliances/README.md
    → docs/domains/memberships/README.md
    → docs/domains/authorization/README.md

docs/domains/events-and-rallies.md
    → docs/domains/events/README.md
    → docs/domains/rallies/README.md

docs/domains/content-management.md
    → docs/domains/content/README.md

docs/domains/contributions-and-reporting.md
    → docs/domains/contributions/README.md

docs/domains/platform-scale-and-administration.md
    → docs/domains/platform/README.md
```

Existing Kingdoms detail guides migrate beneath `docs/domains/kingdoms/`:

```text
kingdoms.md                       → kingdoms/README.md
kingdoms-roster.md                → kingdoms/roster.md
kingdoms-snapshots.md             → kingdoms/snapshots.md
kingdoms-intelligence.md          → kingdoms/intelligence.md
kingdoms-csv-migration.md         → kingdoms/csv-migration.md
kingdoms-transfer-planning.md     → kingdoms/transfer-planning.md
kingdoms-alliance-intelligence.md → kingdoms/alliance-intelligence.md
```

Likewise, `integrations.md`, `notifications.md`, and `recruitment.md` migrate to their domain `README.md` files.

Do not remove a migration-source document until:

1. its authoritative content has been moved;
2. all repository links point to the new location;
3. the domain index is updated;
4. relevant code-local READMEs point to the new location; and
5. documentation architecture tests pass.

## 13. Documentation migration plan

### DOCS-P0 — Standard and structural anchors

- approve this standard;
- update the implementation plan repository structure;
- create all 14 `docs/domains/<domain>/README.md` roots;
- add CI enforcement for the code-domain ↔ docs-domain mapping.

### DOCS-P1 — Populate canonical domain contracts

- migrate combined/flat domain guides into the correct domain directories;
- standardize all 14 domain READMEs to the required living-contract format;
- remove superseded combined guides after links are migrated.

### DOCS-P2 — Capability decomposition

- move existing Kingdoms capability files under `docs/domains/kingdoms/`;
- split other large domains only where the capability-file criteria are met;
- avoid one-file-per-class documentation.

### DOCS-P3 — Code-local README normalization

- standardize all 14 `app/Domain/*/README.md` files;
- require each to link its canonical documentation root;
- document intentional public contracts and dependencies.

### DOCS-P4 — Evidence/index cleanup

- refresh all documentation indexes;
- resolve stale architecture audits and historical links;
- normalize new-file naming without cosmetically rewriting accepted historical evidence.

### DOCS-P5 — Full documentation CI gate

Extend architecture tests to enforce:

- exact domain directory parity;
- required domain `README.md` files;
- lowercase kebab-case capability filenames;
- required index files;
- local Markdown link integrity;
- code-local README → docs-domain links;
- required metadata/headings for canonical living contracts; and
- no reintroduction of superseded flat domain anchors.

## 14. CI enforcement contract

`tests/Architecture/RepositoryStructureTest.php` is the initial documentation-structure gate.

At DOCS-P0 it must enforce:

1. `docs/` contains only the five approved top-level groups;
2. every `app/Domain/<CanonicalDomain>` has exactly one `docs/domains/<domain>/README.md` root;
3. every first-level directory under `docs/domains/` maps back to an actual canonical code domain;
4. documentation filenames remain predictable; and
5. local Markdown links resolve.

The domain parity rule is intentionally bidirectional so documentation cannot silently lag a new code domain and documentation cannot invent a domain that does not exist in the architecture.

## 15. Definition of documentation done

A material change is not documentation-complete until:

- the owning domain directory is identifiable;
- its canonical `README.md` remains correct;
- affected capability documents are updated;
- code-local domain README navigation remains correct;
- authorization, tenancy, privacy, idempotency, failure and integration behavior are documented where applicable;
- affected operations/security/product evidence is updated;
- repository-relative links resolve; and
- documentation architecture tests pass.

The target state is deterministic: code ownership determines documentation ownership, and the repository structure makes that relationship obvious without search or tribal knowledge.
