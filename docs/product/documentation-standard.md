# Repository documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Proposed  
**Applies to:** `docs/`, canonical `app/Domain/*/README.md` files, and documentation-related architecture tests  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This document defines the required structure, naming, ownership, minimum coverage, and standard format for repository documentation.

The goal is deterministic documentation: a developer who sees `app/Domain/Events` should be able to predict that the canonical living domain contract is `docs/domains/events.md`; an operator should be able to predict where operational guidance lives; a reviewer should be able to distinguish current contracts from historical evidence without guessing from filenames.

This standard replaces the looser rule that a runtime domain does not necessarily require its own domain document. Every canonical runtime domain now requires one canonical living domain document, even when several domains participate in one user workflow.

The standard does not make code subordinate to documentation. Code and tests remain authoritative for exact implemented runtime behavior. Documentation defines the intended ownership, business contract, operating model, security boundary, product status, and evidence structure that must remain aligned with implementation.

## 2. Core documentation model

Documentation is organized into two layers.

### 2.1 Code-local domain README

Every canonical runtime domain owns:

```text
app/Domain/<CanonicalDomain>/README.md
```

This file is a concise developer navigation surface. It explains what the module owns, identifies its supported cross-domain contracts, summarizes its internal code layout, and links to the canonical living domain document.

It must not become the full business specification.

### 2.2 Canonical repository documentation

The full living and evidence documentation remains under the five approved top-level groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Do not create parallel top-level documentation groups such as `docs/architecture/`, `docs/runbooks/`, `docs/wiki/`, `docs/features/`, or `docs/reference/`.

Each group owns a distinct class of information:

| Group | Owns | Does not own |
| --- | --- | --- |
| `adr/` | Durable architecture decisions and rationale | Feature instructions, status reporting, operating procedures |
| `domains/` | Current business/domain contracts and code ownership | Product approval, historical phase evidence, runbooks |
| `operations/` | Runtime configuration, operations, observability, deployment, recovery, runbooks | Product scope and business rules |
| `product/` | Baseline plan, product increments, current capability/status, acceptance evidence, documentation governance | Runtime implementation detail that belongs to a domain |
| `security/` | Security baseline, threat models, security reviews, launch-security evidence | General business workflow documentation |

## 3. Universal naming rules

Unless a more specific rule below applies:

- Markdown filenames use lowercase kebab-case.
- Directory indexes are always named `README.md`.
- Names describe ownership first, then the narrower subject: `<domain>-<capability>.md` rather than `<capability>-for-<domain>.md`.
- Do not encode temporary implementation phase names into living domain filenames.
- Do not use vague names such as `notes.md`, `misc.md`, `new-plan.md`, `design2.md`, `final.md`, `current.md`, or `overview-new.md`.
- Do not add dates to living-document filenames. Dates belong in evidence metadata where required.
- Historical/evidence files may keep phase, increment, slice, or acceptance identifiers because those identifiers are part of the evidence identity.
- A rename must preserve or update all repository-relative links in the same change.
- Historical accepted evidence is not renamed merely for cosmetic consistency unless there is a concrete ambiguity or maintenance benefit.

## 4. Standard file taxonomy and naming

### 4.1 `docs/adr/`

Allowed standard forms:

```text
README.md
adr-template.md
NNNN-<decision-name>.md
```

Examples:

```text
0008-domain-first-source-layout.md
0009-documentation-architecture.md
```

Numbered ADRs are immutable decision records except for status/supersession/navigation corrections. A material new decision gets a new number rather than silently rewriting the old rationale.

### 4.2 `docs/domains/`

Required canonical form for every code domain:

```text
<domain>.md
```

Optional capability/detail form:

```text
<domain>-<capability>.md
```

Evidence/audit form:

```text
<subject>-audit.md
```

Examples:

```text
alliances.md
authorization.md
events.md
rallies.md
kingdoms.md
kingdoms-roster.md
kingdoms-transfer-planning.md
kingdoms-alliance-intelligence.md
domain-boundary-audit.md
repository-structure-audit.md
```

A capability document is permitted only when a canonical `<domain>.md` anchor exists and links to it. Capability documents deepen the domain contract; they do not replace the domain anchor.

### 4.3 `docs/operations/`

Global living operating documents use descriptive stable names:

```text
configuration-reference.md
background-processing.md
observability.md
release-checklist.md
production-launch-runbook.md
branch-protection.md
```

Domain/capability operational guides use:

```text
<domain>-operations.md
<domain>-<capability>-operations.md
```

Runbooks use:

```text
runbooks/<procedure>.md
```

Historical phase evidence may use:

```text
phase-<n>-operations.md
phase-<n>-migration-rollback.md
phase-<n>-<evidence-subject>.md
```

New living operational documents must not be named after an implementation slice or PR.

### 4.4 `docs/product/`

Global/current records use stable descriptive names:

```text
implementation-plan.md
current-capability-matrix.md
definition-of-done.md
production-launch-approval.md
production-hardening-exit-report.md
documentation-standard.md
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

Historical baseline phase records use:

```text
phase-<n>-scope.md
phase-<n>-accessibility.md
phase-<n>-exit-report.md
```

Existing historical exceptions such as `phase-1-accessibility-review.md` may remain because they are accepted evidence. New files follow the canonical form.

### 4.5 `docs/security/`

Global living security contract:

```text
security-baseline.md
```

Current or increment/capability security review:

```text
<subject>-security-review.md
```

Pre-runtime decision/security gate when explicitly required:

```text
<subject>-p0-security-review.md
```

Historical phase threat model:

```text
phase-<n>-threat-model.md
```

Production security evidence:

```text
production-launch-security-review.md
```

Security filenames identify the protected subject, not the team or reviewer who authored them.

## 5. One-to-one canonical domain coverage

The canonical source roots are:

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

Each root must have both:

```text
app/Domain/<CanonicalDomain>/README.md
docs/domains/<canonical-domain-kebab>.md
```

The mapping is exact:

| Code domain | Required canonical living doc | Current state at standard introduction | Migration source when applicable |
| --- | --- | --- | --- |
| `Alliances` | `docs/domains/alliances.md` | **Missing** | `identity-tenancy-and-membership.md`, platform/admin and architecture material |
| `Audit` | `docs/domains/audit.md` | **Missing** | security baseline, architecture/outbox and audit implementation evidence |
| `Authorization` | `docs/domains/authorization.md` | **Missing** | `identity-tenancy-and-membership.md` |
| `Content` | `docs/domains/content.md` | **Missing canonical name** | `content-management.md` |
| `Contributions` | `docs/domains/contributions.md` | **Missing canonical name** | `contributions-and-reporting.md` |
| `Events` | `docs/domains/events.md` | **Missing** | `events-and-rallies.md` |
| `Identity` | `docs/domains/identity.md` | **Missing** | `identity-tenancy-and-membership.md` |
| `Integrations` | `docs/domains/integrations.md` | Present | existing canonical guide |
| `Kingdoms` | `docs/domains/kingdoms.md` | Present | existing canonical guide and capability guides |
| `Memberships` | `docs/domains/memberships.md` | **Missing** | `identity-tenancy-and-membership.md` |
| `Notifications` | `docs/domains/notifications.md` | Present | existing canonical guide |
| `Platform` | `docs/domains/platform.md` | **Missing canonical name** | `platform-scale-and-administration.md` |
| `Rallies` | `docs/domains/rallies.md` | **Missing** | `events-and-rallies.md` |
| `Recruitment` | `docs/domains/recruitment.md` | Present | existing canonical guide |

There are therefore **10 missing canonical one-to-one domain anchors** at the time this standard is introduced. Some already have substantial content under broader or descriptive filenames; those files are migration sources, not evidence that the canonical anchor is unnecessary.

## 6. Rules for splitting current combined domain guides

The current broad guides helped explain user workflows, but they blur code ownership. Migration must preserve useful workflow context while establishing one domain anchor per code module.

### `identity-tenancy-and-membership.md`

Split authoritative ownership into:

- `identity.md` — user identity, authentication, MFA, profile/account lifecycle;
- `alliances.md` — Alliance aggregate, creation, settings, active-alliance context and alliance-owned composition surfaces;
- `memberships.md` — membership and invitation lifecycle; and
- `authorization.md` — roles, permissions, policies, grants and role assignment.

Cross-domain workflow explanations should live in the domain that owns the action and link to the other canonical domain documents.

After the split is complete, `identity-tenancy-and-membership.md` should be removed rather than kept as a competing source of truth.

### `events-and-rallies.md`

Split into:

- `events.md` — schedules, occurrences, recurrence, registration and attendance; and
- `rallies.md` — rally guidance, formations, groups, assignments and participation behavior.

If a cross-domain event+rally workflow needs a narrative, place that narrative in one owning document and link across. Do not recreate a third canonical combined file.

### Single-domain descriptive filenames

These should be normalized into their canonical domain anchors:

- `content-management.md` → `content.md`;
- `contributions-and-reporting.md` → `contributions.md`; and
- `platform-scale-and-administration.md` → `platform.md`.

The content can be migrated substantially intact, but it must be reshaped to the standard domain-document section order.

## 7. Rules for capability documents

Large domains may require more than one document. The canonical anchor remains the map and owner contract.

For `Kingdoms`, the existing pattern is valid:

```text
kingdoms.md
kingdoms-roster.md
kingdoms-snapshots.md
kingdoms-intelligence.md
kingdoms-csv-migration.md
kingdoms-transfer-planning.md
kingdoms-alliance-intelligence.md
```

The rule is:

```text
<domain>.md                         # required anchor
<domain>-<capability>.md            # optional living detail
```

Create a capability document when at least one of these is true:

- the capability has a distinct lifecycle with meaningful invariants;
- the capability has a distinct authorization/privacy boundary;
- the capability has substantial persistence or query semantics;
- the capability has its own import/export/integration contract;
- the anchor would otherwise become difficult to navigate; or
- multiple teams/review paths need a stable linkable contract.

Do not create one Markdown file per model, controller, action, query, enum, or database table.

## 8. Standard format for code-local domain README files

Every `app/Domain/<Domain>/README.md` uses this exact section order.

```markdown
# <Domain> domain

## Purpose

One short paragraph stating what this code module owns.

## Owned code

- `Actions/` — ...
- `Queries/` — ...
- `Models/` — ...
- `Services/` — ...
- `Http/` — ...
- `Enums/` / `ValueObjects/` — ...

List only directories/types that actually exist or are materially important.

## Public contracts

List the actions, queries, services, value objects, enums, or events that other domains are intentionally allowed to use.

## Dependencies

List intentional cross-domain dependencies and why they exist. Do not list framework/library dependencies.

## Canonical documentation

- [`docs/domains/<domain>.md`](../../../docs/domains/<domain>.md)
- relevant capability, operations, security or product links only when material
```

Rules:

- Keep the code-local README concise; normally 30–100 lines.
- Do not duplicate full lifecycle/security/operations descriptions from `/docs`.
- Do not describe historical phase status as the primary purpose of a living code README.
- Every code-local README must link its canonical `docs/domains/<domain>.md` file.
- If the code layout changes materially, update the `Owned code` section in the same change.

## 9. Standard format for canonical living domain documents

Every required `docs/domains/<domain>.md` file uses the following section order. A section may state `Not applicable` with a short reason; it should not silently disappear when the subject is relevant to other domains.

```markdown
# <Domain> domain

[← Domain documentation](README.md)

**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/<Domain>`  
**Primary authorization boundary:** `<permission/policy/cross-tenant grant or N/A>`

## 1. Purpose and ownership

What this domain owns and why it exists.

## 2. Scope

### In scope

- ...

### Out of scope

- ...

## 3. Domain model

Describe important entities, value objects, enums and relationships in business terms.

## 4. Core invariants

Number stable rules that must always hold.

## 5. Lifecycles and workflows

Describe state transitions and principal workflows. Link to capability documents for deep workflows.

## 6. Authorization and tenancy

Document tenant scoping, policies/permissions, privileged confirmation, platform grants and cross-tenant rules.

## 7. Cross-domain contracts

### Consumes

Intentional contracts consumed from other domains.

### Exposes

Intentional actions, queries, services, values or events exposed to other domains.

## 8. Persistence and data ownership

Document owned persistence, reference-versus-tenant data, history/retention semantics and important uniqueness/index/concurrency rules.

## 9. Events, outbox and integrations

Document domain events, transactional-outbox use, external exposure boundaries and idempotency where applicable.

## 10. HTTP, UI and API surfaces

Describe route/workspace/API ownership at a stable contract level; do not inventory every controller method.

## 11. Background processing

Schedulers, queues, jobs, retries and recovery, or `Not applicable`.

## 12. Failure, idempotency and concurrency

Describe retry semantics, locks/serialization, duplicate handling and fail-closed behavior.

## 13. Security and privacy

Summarize domain-specific controls and link to security evidence rather than duplicating entire threat models.

## 14. Observability and operations

Signals, operator concerns and links to living operations/runbooks.

## 15. Testing and architecture enforcement

List the important feature, integration, tenant-isolation, performance and architecture test surfaces.

## 16. Explicit non-capabilities

State important things the domain deliberately does not do.

## 17. Related documentation

Link only authoritative adjacent contracts/evidence.
```

### Domain-document writing rules

- Describe stable business behavior and invariants, not line-by-line implementation.
- Name important classes when they are architectural entry points, but do not maintain fragile exhaustive class counts.
- Use repository-relative links to important source files/directories when they help navigation.
- State missing versus zero, active versus archived, current versus historical, and tenant versus global semantics explicitly where relevant.
- State authorization in policy/permission terms rather than UI-role assumptions.
- State idempotency and failure behavior for all material mutations and asynchronous work.
- State external API/webhook exposure explicitly; internal events are not assumed public.
- Do not copy acceptance evidence into a living domain contract. Link to the evidence.

## 10. Standard metadata by document type

### Living contract

Required immediately after the title/backlink:

```markdown
**Document type:** Living domain contract  
**Status:** Current  
**Code owner:** `app/Domain/<Domain>`
```

Do not require a fixed commit SHA in a living document because it would become stale on every harmless change.

### Audit/evidence record

Required:

```markdown
**Document type:** Audit evidence  
**Status:** Current | Accepted | Superseded  
**Audited/validated at:** `<commit SHA>`  
**Evidence date:** YYYY-MM-DD
```

### Product increment scope

Required:

```markdown
**Document type:** Product increment scope  
**Scope ID:** `<ID>`  
**Status:** Proposed | Approved | Superseded
```

### Implementation plan

Required:

```markdown
**Document type:** Implementation plan  
**Scope:** `<baseline or increment ID>`  
**Status:** Planned | In progress | Complete
```

### Slice validation

Required:

```markdown
**Document type:** Validation evidence  
**Scope:** `<ID / slice>`  
**Status:** Candidate | Validated  
**Validated implementation:** `<commit SHA>`
```

### Exit report

Required:

```markdown
**Document type:** Acceptance evidence  
**Scope:** `<ID>`  
**Status:** Accepted  
**Accepted implementation:** `<commit SHA>`
```

### Security review

Required:

```markdown
**Document type:** Security review  
**Scope:** `<domain/capability/increment>`  
**Status:** Candidate | Accepted | Current  
**Implementation/evidence SHA:** `<commit SHA when evidence-bound>`
```

### Operations guide

Required:

```markdown
**Document type:** Living operations guide  
**Status:** Current  
**Runtime owner:** `<domain/platform/operations>`
```

### Runbook

Required:

```markdown
**Document type:** Runbook  
**Status:** Current  
**Operator:** `<role/responsibility>`
```

## 11. Standard format for ADRs

ADRs continue to use `docs/adr/adr-template.md` as the concrete template. At minimum every ADR contains:

1. Title and number.
2. Status.
3. Context/problem.
4. Decision.
5. Alternatives considered.
6. Consequences/tradeoffs.
7. Security/operations implications when applicable.
8. Supersession relationship when applicable.

ADRs explain **why architecture is shaped a certain way**. They do not become feature manuals.

## 12. Standard format for product increment scope files

Every `<increment-slug>-increment.md` uses this order:

1. Purpose/outcome.
2. User/business problem.
3. Scope.
4. Explicit non-goals.
5. Dependencies.
6. Domain ownership.
7. Identity/tenancy/privacy model.
8. Authorization model.
9. Data/persistence rules.
10. Integration/event/API boundaries.
11. Security/abuse requirements.
12. Operations/observability requirements.
13. Accessibility requirements.
14. Acceptance criteria.
15. Deferred follow-on work.
16. Production-launch implications.

An increment scope approves product boundaries; it must not claim runtime implementation that has not passed its implementation/acceptance gates.

## 13. Standard format for increment implementation plans

Every `<increment-slug>-implementation-plan.md` uses this order:

1. Scope and status.
2. Locked dependencies.
3. Cross-cutting decisions/P0 gate.
4. Slice table with stable IDs.
5. For each slice: outcome, schema/data, behavior, authorization/privacy, UI/API, tests, operations, explicit exclusions, exit gate.
6. Whole-increment hardening/acceptance gate.
7. Migration/rollback order.
8. Protected validation expectations.
9. Documentation/evidence outputs.

The plan must distinguish candidate, validated slice, and accepted whole increment.

## 14. Standard format for validation records

Every `*-slice-<id>-validation.md` uses:

1. Scope and exact validated implementation SHA.
2. What was validated.
3. Behavioral coverage.
4. Authorization/tenant-isolation coverage.
5. Security/privacy coverage.
6. Migration/rollback coverage.
7. Accessibility coverage.
8. Performance/query coverage where applicable.
9. Protected check/run evidence.
10. Known boundaries/non-capabilities.
11. Result: Candidate or Validated.

Do not use a validation document as the living contract after the slice is accepted.

## 15. Standard format for exit reports

Every `*-exit-report.md` uses:

1. Scope and acceptance statement.
2. Exact accepted implementation anchor.
3. Delivered capabilities.
4. Whole-increment hardening performed.
5. Security/privacy result.
6. Tenant/authorization result.
7. Accessibility result.
8. Migration/rollback result.
9. Performance/query result.
10. Operations/recovery result.
11. Integration/API/webhook result.
12. Protected evidence identifiers.
13. Deferred/non-capabilities.
14. Production-launch boundary.

Exit reports are historical acceptance evidence and are not rewritten into current operating guides.

## 16. Standard format for security reviews

Every current or evidence-bound security review uses:

1. Scope/assets.
2. Trust boundaries.
3. Identity and authorization model.
4. Tenant-isolation model.
5. Sensitive/private data.
6. Threats and abuse cases.
7. Controls.
8. Event/API/integration exposure.
9. Logging/audit/privacy considerations.
10. Failure/recovery risks.
11. Verification/tests/evidence.
12. Residual risks and explicit deferrals.
13. Decision/result.

A security review should link to the living domain contract rather than restating all normal business behavior.

## 17. Standard format for living operations guides

Every living operations guide uses:

1. Purpose/runtime ownership.
2. Components and dependencies.
3. Configuration.
4. Normal operation.
5. Scheduler/queue/background behavior.
6. Health and observability.
7. Failure modes and diagnosis.
8. Safe retry/recovery.
9. Data/migration implications.
10. Capacity/performance considerations.
11. Security/secrets considerations.
12. Related runbooks.

Commands should be copyable and include prerequisites/expected result where material.

## 18. Standard format for runbooks

Every `operations/runbooks/<procedure>.md` uses:

1. Purpose.
2. Preconditions/prerequisites.
3. Safety and stop conditions.
4. Required access/tools.
5. Procedure.
6. Validation.
7. Rollback/recovery.
8. Evidence to retain.
9. Escalation/incident handoff.
10. Related documentation.

A runbook must be executable by an operator who did not author the feature.

## 19. README/index standard

Every canonical documentation directory has a `README.md` that is an index, not a second implementation specification.

Each index must contain:

1. Purpose of the directory.
2. Start-here/current documents.
3. Complete inventory of canonical living documents in that directory.
4. Historical/evidence section when applicable.
5. Naming/ownership rule or link to this standard.
6. Clear explanation of which document answers which class of question.

`docs/domains/README.md` must list every one of the 14 canonical domain anchors once they exist.

## 20. Source-of-truth precedence

When documents overlap, use this precedence:

1. Code and tests for exact implemented runtime behavior.
2. Approved baseline/increment scope for authorized product scope.
3. Accepted ADRs for architecture decisions and rationale.
4. Canonical living `docs/domains/<domain>.md` for current domain behavior/ownership.
5. Living operations/security baseline documents for current operational/security contracts.
6. Current capability/production decision records for status/go-no-go state.
7. Validation, exit, phase and audit documents as historical/evidence records.

A lower-precedence document must link upward rather than redefine a higher-precedence contract.

## 21. Duplication rules

Documentation should repeat only enough context to make a document usable.

Do not duplicate:

- full role/permission matrices across multiple domain docs;
- full event payload schemas in both domain and security docs;
- full migration procedures in both domain and operations docs;
- acceptance check lists in living docs;
- historical protected-run identifiers in current domain guides; or
- the same business invariant in multiple canonical owners.

Prefer a short summary plus an authoritative repository-relative link.

## 22. Documentation change rules

A code change requires documentation changes when it materially changes any of:

- domain ownership;
- lifecycle/state transitions;
- authorization/permission behavior;
- tenant isolation;
- persistence/data semantics;
- public or cross-domain contract;
- event/outbox/API/webhook behavior;
- import/export behavior;
- scheduler/queue/retry behavior;
- configuration;
- observability/health;
- recovery/rollback;
- security/privacy controls;
- accessibility behavior; or
- implemented product capability/status.

Pure refactoring that does not change any documented contract does not require noisy documentation edits merely to touch a file.

## 23. Required migration work

Adopt this standard in controlled steps.

### DOCS-P0 — Lock the documentation contract

- accept this standard;
- update indexes to link it;
- update repository/domain structure tests to encode the canonical naming rules;
- keep existing living files available until their replacement is complete.

### DOCS-P1 — Create canonical domain anchors

Create and populate the 10 missing canonical files:

```text
docs/domains/alliances.md
docs/domains/audit.md
docs/domains/authorization.md
docs/domains/content.md
docs/domains/contributions.md
docs/domains/events.md
docs/domains/identity.md
docs/domains/memberships.md
docs/domains/platform.md
docs/domains/rallies.md
```

Retain and standardize the 4 already canonical anchors:

```text
docs/domains/integrations.md
docs/domains/kingdoms.md
docs/domains/notifications.md
docs/domains/recruitment.md
```

### DOCS-P2 — Retire competing broad guides

After content has been moved and all links updated, remove:

```text
docs/domains/identity-tenancy-and-membership.md
docs/domains/events-and-rallies.md
docs/domains/content-management.md
docs/domains/contributions-and-reporting.md
docs/domains/platform-scale-and-administration.md
```

Do not keep redirect-style Markdown stubs indefinitely; Git history preserves the old paths.

### DOCS-P3 — Normalize code-local READMEs

Update every `app/Domain/*/README.md` to the code-local standard and require the canonical domain-doc link.

Existing minimal phase-only README text is insufficient because it does not describe current code ownership or supported contracts.

### DOCS-P4 — Normalize living operations/security documentation

Normalize current living files when touched, and create missing capability operations/security guides when a capability has a real distinct operational/security contract.

Do not churn accepted historical evidence solely to rename it.

### DOCS-P5 — Add CI enforcement

Add or extend architecture/documentation tests to verify:

- exactly the five top-level `docs/` groups;
- all 14 canonical `app/Domain/<Domain>` roots;
- all 14 canonical `docs/domains/<domain>.md` files;
- all 14 code-local domain `README.md` files;
- each code-local README links to its canonical domain document;
- canonical domain documents contain required metadata and required headings;
- descriptive filenames use lowercase kebab-case;
- directory indexes are `README.md`;
- no prohibited parallel documentation directories appear; and
- repository-relative Markdown links resolve in CI.

## 24. Proposed architecture-test contract

The documentation tests should derive the canonical domain map once rather than maintain unrelated lists in multiple tests.

Conceptually:

```text
Alliances      -> alliances.md
Audit          -> audit.md
Authorization  -> authorization.md
Content        -> content.md
Contributions  -> contributions.md
Events         -> events.md
Identity       -> identity.md
Integrations   -> integrations.md
Kingdoms       -> kingdoms.md
Memberships    -> memberships.md
Notifications  -> notifications.md
Platform       -> platform.md
Rallies        -> rallies.md
Recruitment    -> recruitment.md
```

The test should validate presence and structure, not assert fragile line counts or exact prose.

## 25. Definition of documentation complete

A material domain capability is documentation-complete when:

- the owning `app/Domain/<Domain>/README.md` is current;
- the canonical `docs/domains/<domain>.md` contract is current;
- any warranted capability document is current;
- security evidence is updated when the threat/control boundary changed;
- operations/runbooks are updated when runtime operation changed;
- product capability/status records are updated when delivered scope changed;
- ADRs are updated/added when architecture changed;
- links and indexes are current;
- historical evidence remains clearly historical; and
- the documentation-structure/link checks pass.

## 26. Immediate gaps this standard closes

The current repository has strong documentation volume but inconsistent mapping between code ownership and living docs. Examples include:

- `Events` and `Rallies` having separate code domains but one combined living guide;
- `Identity`, `Alliances`, `Memberships`, and `Authorization` being collapsed into one guide;
- `Content`, `Contributions`, and `Platform` having living filenames that do not match their canonical code-domain names;
- `Audit` having runtime ownership but no canonical domain anchor;
- code-local domain READMEs ranging from minimal phase-era summaries to a much richer Kingdoms contract; and
- structure audits containing historical statements that can become stale after accepted increments.

The target model is simple: **one code domain, one predictable canonical living domain document, optional domain-prefixed capability documents, and one standard format per document type.**

## 27. Non-goals

This standard does not:

- require one Markdown file per class/table/route;
- move source code to mirror documentation;
- create a sixth `docs/` top-level group;
- rewrite accepted historical evidence merely for stylistic consistency;
- make product acceptance equivalent to production approval; or
- replace architecture/security/operations tests with prose.

It establishes a deterministic documentation architecture so future code and product work has one predictable place to document ownership, behavior, operations, security, and evidence.
