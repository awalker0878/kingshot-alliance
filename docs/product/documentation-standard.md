# Repository documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Applies to:** `docs/`, canonical `app/Domain/*/README.md` files, and documentation-related architecture tests  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This document defines the required structure, naming, ownership, minimum coverage, and standard format for repository documentation.

The goal is deterministic documentation: a contributor who sees `app/Domain/Events` can derive the canonical living contract without repository search:

```text
app/Domain/Events/
        ↕
docs/domains/events/README.md
```

Code and tests remain authoritative for exact implemented runtime behavior. Documentation defines intended ownership, business contracts, operating/security boundaries, product status, and evidence structure that must stay aligned with implementation.

## 2. Canonical documentation groups

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

| Group | Owns | Does not own |
| --- | --- | --- |
| `adr/` | Durable architecture decisions and rationale | Feature instructions, status reporting, runbooks |
| `domains/` | Current business/domain contracts and code ownership | Product approval, historical phase evidence, runbooks |
| `operations/` | Runtime configuration, operations, observability, deployment, recovery, runbooks | Product scope/business ownership |
| `product/` | Baseline plan, increments, current capability/status, acceptance/evidence, architecture audits, documentation governance | Feature runtime detail owned by a domain |
| `security/` | Security baseline, threat models, security reviews, launch-security evidence | General business workflow documentation |

Every group has a `README.md` navigation index.

## 3. Canonical domain-documentation structure

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
    roster.md
    snapshots.md
    intelligence.md
    csv-migration.md
    transfer-planning.md
    alliance-intelligence.md
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

### Mandatory one-to-one rule

For every canonical code domain:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>/README.md
```

Both files are required.

- The code-local README is concise developer navigation.
- The docs-domain README is the full canonical living contract.
- Additional capability files live inside the owning domain folder.
- `docs/domains/README.md` is the **only** Markdown file permitted directly under `docs/domains/`.
- Cross-domain/repository architecture audits belong under `docs/product/`, not as flat domain-root files.

The mapping is bidirectional: a docs-domain directory without a matching code domain is also invalid.

## 4. Capability-document rule

A large domain may add:

```text
docs/domains/<domain>/<capability>.md
```

Create a capability document when at least one is true:

- the capability has a distinct lifecycle with meaningful invariants;
- it has a distinct authorization/privacy boundary;
- it has substantial persistence/query semantics;
- it owns a significant import/export/integration contract;
- the domain README would otherwise be difficult to navigate; or
- it needs a stable review/operational link independent of the root contract.

Do **not** create one Markdown file per model, controller, route, table, action, query, enum, or value object.

The folder already identifies ownership, so capability filenames do not repeat the domain name. Example:

```text
docs/domains/kingdoms/roster.md
```

not:

```text
docs/domains/kingdoms/kingdoms-roster.md
```

## 5. Universal naming rules

Unless a more specific rule applies:

- Markdown filenames use lowercase kebab-case.
- Directory indexes are always `README.md`.
- Domain directory names are lowercase kebab-case forms of canonical code-domain names.
- Capability files use `<capability>.md` inside the owning domain folder.
- Living filenames do not encode temporary implementation phase/slice/PR names.
- Do not use vague names such as `notes.md`, `misc.md`, `new-plan.md`, `design2.md`, `final.md`, or `overview-new.md`.
- Dates belong in evidence metadata, not living filenames.
- Historical/evidence filenames may retain phase, increment, slice, or acceptance identifiers because those identifiers are part of the evidence identity.
- Renames/moves update repository-relative links in the same change.
- Accepted historical evidence is not renamed merely for cosmetic consistency.

## 6. File taxonomy outside domain folders

### 6.1 `docs/adr/`

```text
README.md
adr-template.md
NNNN-<decision-name>.md
```

A material architecture decision receives a new numbered ADR. If it replaces an earlier ADR, mark the earlier record superseded instead of rewriting its historical rationale.

### 6.2 `docs/operations/`

Global living documents use stable descriptive names, for example:

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

Historical phase operating evidence may retain `phase-<n>-...` naming.

### 6.3 `docs/product/`

Global/current records use stable names such as:

```text
implementation-plan.md
current-capability-matrix.md
definition-of-done.md
production-launch-approval.md
production-hardening-exit-report.md
documentation-standard.md
repository-structure-audit.md
domain-boundary-audit.md
```

Named product increments use one stable slug consistently:

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

Canonical forms include:

```text
security-baseline.md
<subject>-security-review.md
<subject>-p0-security-review.md
phase-<n>-threat-model.md
production-launch-security-review.md
```

Security filenames identify the protected subject, not the author/team.

## 7. Standard code-local domain README format

Every `app/Domain/<Domain>/README.md` uses this section order:

```markdown
# <Domain> domain

## Purpose

One short paragraph stating what the module owns.

## Owned code

Describe the important runtime code/types owned beneath this domain root. List only directories/types that actually exist or matter architecturally.

## Public contracts

List intentional cross-domain actions, queries, services, value objects, enums, events, or supported state consumed elsewhere.

## Dependencies

List intentional cross-domain dependencies and why they exist. Do not inventory framework/library dependencies.

## Canonical documentation

- [`docs/domains/<domain>/`](../../../docs/domains/<domain>/README.md)
```

Rules:

- normally concise (roughly 30–100 lines);
- do not duplicate full lifecycle/security/operations detail from `/docs`;
- historical phase status is not the primary description;
- always link the matching docs-domain README; and
- update the Owned code/public-contract sections when the module boundary materially changes.

## 8. Standard canonical domain README format

Every `docs/domains/<domain>/README.md` uses the following section order. A section may say `Not applicable` with a short reason; relevant contract areas must not silently disappear.

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

### Domain writing rules

- describe stable business behavior/invariants, not line-by-line implementation;
- name important classes only when they are architectural entry points;
- avoid fragile exhaustive class/file counts;
- state tenant/global, missing/zero, active/archived, and current/historical semantics explicitly where relevant;
- state authorization in policy/permission terms, not only UI role names;
- link deep capability contracts instead of duplicating them; and
- link security/operations evidence instead of embedding entire threat models/runbooks.

## 9. Standard capability document format

A capability file uses:

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

Capability documents deepen the root contract; they never redefine ownership established by the domain README.

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

### 10.2 Increment implementation plan

Required sections:

1. Purpose/dependency baseline.
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
3. Tests/checks.
4. Security/tenant/privacy assertions.
5. Migration/rollback evidence.
6. Performance/query evidence where relevant.
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
3. Security/privacy result.
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
3. Attackers/abuse cases.
4. Authorization/tenant threats.
5. Privacy/data-exposure threats.
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
3. Safety/stop conditions.
4. Procedure.
5. Validation.
6. Rollback/recovery.
7. Escalation.
8. Evidence to retain.

## 11. Source-of-truth precedence

When documentation overlaps:

1. `docs/product/implementation-plan.md` — approved baseline repository/program architecture.
2. Accepted ADRs — material architecture decisions/rationale.
3. Approved named product increment scopes — authorized post-baseline product scope.
4. `docs/product/current-capability-matrix.md` and current approval/status records — present capability/go-no-go state.
5. `docs/domains/<domain>/README.md` plus capability files — current business/runtime contracts.
6. Living operations/security documents — operating/security requirements.
7. Validation, exit, accessibility, audit, and historical phase records — evidence.
8. Code/tests — exact implemented behavior.

A lower-precedence document must not silently redefine a higher-precedence contract. If code/tests and documentation disagree, treat the discrepancy as a defect and reconcile it explicitly.

## 12. Implemented migration state

The former flat domain layout has been fully migrated.

### Combined guides split by ownership

```text
identity-tenancy-and-membership.md
  → identity/README.md
  → alliances/README.md
  → memberships/README.md
  → authorization/README.md

events-and-rallies.md
  → events/README.md
  → rallies/README.md

content-management.md
  → content/README.md

contributions-and-reporting.md
  → contributions/README.md

platform-scale-and-administration.md
  → platform/README.md
```

### Single-domain guides moved to canonical roots

```text
integrations.md → integrations/README.md
notifications.md → notifications/README.md
recruitment.md → recruitment/README.md
```

### Kingdoms capability guides nested

```text
kingdoms.md                       → kingdoms/README.md
kingdoms-roster.md                → kingdoms/roster.md
kingdoms-snapshots.md             → kingdoms/snapshots.md
kingdoms-intelligence.md          → kingdoms/intelligence.md
kingdoms-csv-migration.md         → kingdoms/csv-migration.md
kingdoms-transfer-planning.md     → kingdoms/transfer-planning.md
kingdoms-alliance-intelligence.md → kingdoms/alliance-intelligence.md
```

### Architecture audits relocated

```text
docs/domains/repository-structure-audit.md
  → docs/product/repository-structure-audit.md

docs/domains/domain-boundary-audit.md
  → docs/product/domain-boundary-audit.md
```

Superseded flat files are removed after content/link migration; no redirect/stub compatibility files are retained.

## 13. Implemented documentation architecture work

The documentation-standardization increment completed these work packages:

- **DOCS-P0 — Standard and structural anchors:** standard, implementation-plan alignment, 14 mirrored domain roots, initial parity CI.
- **DOCS-P1 — Canonical domain contracts:** all 14 roots populated using the standard living-domain format; combined guides removed after migration.
- **DOCS-P2 — Capability decomposition:** Kingdoms capability files moved beneath `docs/domains/kingdoms/`; no one-file-per-class sprawl introduced.
- **DOCS-P3 — Code-local README normalization:** all 14 `app/Domain/*/README.md` files use the standard developer-navigation structure and canonical links.
- **DOCS-P4 — Evidence/index cleanup:** documentation indexes/current architecture/status links updated; repository/domain audits moved to product evidence.
- **DOCS-P5 — CI structure gate:** architecture tests enforce domain parity, required READMEs, predictable filenames, local links, and no flat domain-root Markdown reintroduction.

Future documentation changes maintain this structure; they do not reopen the migration work packages.

## 14. CI enforcement contract

`tests/Architecture/RepositoryStructureTest.php` is the canonical documentation-structure gate and runs in normal protected CI.

It enforces:

1. `docs/` contains only the five approved top-level groups.
2. Required documentation indexes exist.
3. First-level `app/Domain/*` and `docs/domains/*` directories match bidirectionally after canonical kebab-case normalization.
4. Every docs-domain directory contains `README.md`.
5. `docs/domains/` contains no root Markdown file except `README.md`.
6. Descriptive Markdown filenames use lowercase kebab-case (`README.md` and numbered ADR conventions are the documented exceptions).
7. Local Markdown links resolve.
8. Legacy uppercase/underscore Markdown references do not reappear.
9. Path-like Markdown references in code spans resolve.
10. Test roots remain within the approved canonical groups.

The parity rule means CI fails if:

- a code domain is introduced without matching documentation;
- a docs-domain is invented without matching code;
- a docs-domain loses its README; or
- a flat living domain file is reintroduced at `docs/domains/*.md`.

## 15. Definition of documentation done

A material change is not documentation-complete until:

- the owning domain directory is identifiable;
- its canonical README remains correct;
- affected capability documents are updated;
- the code-local domain README remains correct;
- authorization, tenancy, privacy, idempotency, failure, and integration behavior are documented where applicable;
- affected operations/security/product evidence is updated;
- indexes/current capability navigation are updated when necessary;
- repository-relative links resolve; and
- documentation architecture/protected CI checks pass on the exact final head.

The end state is deterministic: code ownership determines documentation ownership, and repository structure makes that relationship obvious without search or tribal knowledge.
