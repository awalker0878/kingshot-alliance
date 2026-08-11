# Repository documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Applies to:** `docs/`, canonical `app/Domain/*/README.md` files, and documentation-related architecture tests  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This standard defines repository documentation ownership, structure, naming, base formats, source-of-truth precedence, and the stable structural rules protected by CI.

The governing principle is:

> Documentation about a code/domain's business ownership and domain-specific behavior/evidence belongs with that domain. Top-level documentation areas describe the overall program, shared runtime/policy, durable architecture, historical phase-wide evidence, or production decisions.

Code and tests remain authoritative for exact implemented runtime behavior. Normal change-time obligations after DCP are governed by [Documentation maintenance standard](documentation-maintenance-standard.md).

## 2. Canonical top-level groups

The repository has exactly five top-level documentation groups:

```text
docs/
  adr/
  domains/
  operations/
  product/
  security/
```

Do not create parallel groups such as `docs/architecture/`, `docs/runbooks/`, `docs/wiki/`, `docs/features/`, or `docs/reference/`.

- `docs/adr/` — current system architecture index and durable architecture decisions/rationale.
- `docs/domains/` — current domain contracts and domain-specific product/security/operations/interfaces/testing evidence.
- `docs/operations/` — shared runtime/configuration/observability/deployment/recovery/runbooks and historical phase-wide operating evidence.
- `docs/product/` — cross-program baseline/governance/status/current architecture/capability/audits/DCP/historical phase-wide acceptance/production decisions.
- `docs/security/` — shared security baseline, historical phase-wide threat evidence, production security boundary.

Every top-level group has `README.md` navigation.

## 3. Canonical domain ownership

Canonical code domains are:

`Alliances`, `Audit`, `Authorization`, `Content`, `Contributions`, `Events`, `Identity`, `Integrations`, `Kingdoms`, `Memberships`, `Notifications`, `Platform`, `Rallies`, `Recruitment`.

Documentation mirrors code ownership exactly:

```text
app/Domain/<CanonicalDomain>/README.md
        ↕
docs/domains/<canonical-domain-kebab>/README.md
```

`docs/domains/README.md` is the only Markdown file permitted directly beneath `docs/domains/`.

The code-local README is concise developer navigation. The docs-domain README is the canonical living business/runtime contract.

## 4. Domain-owned documentation families

A domain may own:

```text
docs/domains/<domain>/README.md
```

for the root living contract;

```text
docs/domains/<domain>/<capability>.md
```

for material capability contracts;

```text
docs/domains/<domain>/product/
```

for domain-specific approved scopes/plans/decisions/validation/accessibility/exit evidence;

```text
docs/domains/<domain>/security/
```

for living security profiles and focused reviews;

```text
docs/domains/<domain>/operations/
```

for living operational profiles and focused runbooks;

```text
docs/domains/<domain>/interfaces/
```

for living interface profiles and compatibility-sensitive contracts; and

```text
docs/domains/<domain>/testing/
```

for current validation/evidence maps.

Do not create one document per model/controller/route/table/action/query/test/class. Split only when a distinct lifecycle, compatibility boundary, risk/operations concern, or material acceptance record warrants it.

## 5. Naming

- Descriptive Markdown filenames use lowercase kebab-case.
- Directory indexes use `README.md`.
- Numbered ADR filenames are accepted exceptions.
- Domain directory names are lowercase kebab forms of code-domain names.
- Capability filenames normally use `<capability>.md` without repeating the domain name.
- Living filenames do not encode temporary PR/slice names.
- Historical evidence may retain phase/increment/slice IDs when they form part of immutable record identity.
- Avoid vague filenames such as `notes.md`, `misc.md`, `new-plan.md`, `design2.md`, or `final.md`.
- Moves/renames update repository-relative links in the same change.
- Do not retain empty compatibility/stub files solely to preserve old internal documentation paths.

## 6. Code-local README format

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

Keep it concise. Update it when ownership, supported public contracts, or material dependencies change.

## 7. Canonical domain contract format

Every `docs/domains/<domain>/README.md` uses this section order:

1. Purpose and ownership.
2. Scope.
3. Domain model.
4. Core invariants.
5. Lifecycles and workflows.
6. Authorization and tenancy.
7. Cross-domain contracts.
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

The contract describes stable business/runtime behavior, not implementation line-by-line.

## 8. Focused document and evidence formats

Focused standards define their own detailed formats:

- [Domain contract standard](domain-contract-standard.md)
- [Security documentation standard](security-documentation-standard.md)
- [Operations documentation standard](operations-documentation-standard.md)
- [Interface documentation standard](interface-documentation-standard.md)
- [Testing/evidence standard](testing-evidence-standard.md)
- [Architecture/governance standard](architecture-governance-standard.md)
- [Documentation maintenance standard](documentation-maintenance-standard.md)

Historical product/validation/exit evidence must record immutable revision/check identity where required by its owning standard. Security reviews own threat/control/residual-risk evidence. Operations guides/runbooks own state/diagnosis/recovery/operator safety. Interface contracts own externally observable compatibility. Testing maps own current validation discoverability.

## 9. Source-of-truth precedence

When documentation overlaps, use the narrowest applicable authority:

1. accepted implementation-plan baseline and approved named scopes for authorized product scope;
2. accepted ADRs for durable architecture decisions;
3. code/tests for exact current implementation behavior;
4. canonical domain/capability contracts for current business/runtime ownership;
5. domain security/operations/interfaces/testing profiles for specialized current concerns;
6. current system capability/dependency/audit/navigation for repository-level summaries;
7. historical phase/increment/DCP evidence for accepted evidence at recorded revisions.

A summary must not silently redefine its owner. Documentation drift is a defect, not a compatibility state to preserve.

## 10. Current versus historical records

Living documents evolve with current accepted behavior. Historical evidence records a decision/validation/acceptance at a specific point in time.

Historical evidence may receive broken-link repair, labeled factual errata, recovered immutable identity, or supersession pointers. Do not silently recompute historical test counts or rewrite old rationale as current truth.

## 11. Documentation Completion Program

The DCP progressively established:

- P1 domain/code ownership completeness;
- P2 security/privacy completeness;
- P3 operations/recovery completeness;
- P4 interfaces/integrations completeness;
- P5 testing/evidence traceability completeness;
- P6 system architecture/program-governance consolidation; and
- P7 change-driven maintenance/final acceptance.

The [program plan](documentation-program-plan.md), [completeness standard](documentation-completeness-standard.md), and [program status](documentation-program-status.md) retain program control/evidence. After P7 completes, there is no P8; normal documentation changes follow the maintenance standard.

## 12. CI enforcement

Stable documentation architecture is protected by architecture tests. Together they enforce, as applicable:

- exactly five top-level groups;
- code-domain/docs-domain parity;
- code-local and canonical README requirements;
- P1–P5 specialized domain profile requirements;
- no flat domain living files at the domains root;
- filename/path rules;
- local Markdown links;
- shared/domain evidence placement;
- ADR lifecycle/indexing;
- interface/route inventory rules;
- operations/security/testing/architecture governance inventories; and
- final P7 standards/index/maintenance consistency.

Final P7 automation aggregates stable rules rather than duplicating every phase-specific assertion or parsing every implementation detail.

## 13. Definition of documentation done

A material change is documentation-complete only when all applicable [maintenance obligations](documentation-maintenance-standard.md) are satisfied, including:

- owner/current contracts remain accurate;
- affected specialized profiles/focused documents are updated;
- code-local navigation remains accurate when ownership/contracts/dependencies change;
- cross-domain/system navigation changes only when system direction changes;
- historical evidence remains historical;
- indexes and links resolve;
- current status vocabulary remains correct; and
- protected documentation architecture checks pass on the exact final head.

The stable end state remains: **code ownership determines documentation ownership; top-level documentation describes the overall program/shared platform; historical evidence preserves accepted history; current documentation tracks current accepted behavior.**
