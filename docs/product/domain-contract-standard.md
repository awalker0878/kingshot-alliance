# Domain contract documentation standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Primary phase:** `DCP-P1`  
**Applies to:** `app/Domain/*/README.md`, `docs/domains/*/README.md`, and living domain capability documents

## 1. Purpose

This standard defines the minimum documentation needed to prove a code domain is completely documented as a living business/runtime contract.

The goal is not one Markdown file per class. The goal is deterministic navigation from code ownership to the business rules, state, contracts, failure behavior, and material capabilities that a contributor must understand before changing that domain.

## 2. Required artifact set

Every canonical code domain requires:

```text
app/Domain/<Domain>/README.md
docs/domains/<domain>/README.md
```

A domain also requires one or more capability documents when a material capability satisfies the split rules in this standard.

The domain root remains authoritative for ownership and whole-domain invariants. Capability documents deepen independently meaningful behavior without redefining domain ownership.

## 3. Code-local README contract

Every `app/Domain/<Domain>/README.md` must contain, in this order:

1. `# <Domain> domain`
2. `## Purpose`
3. `## Owned code`
4. `## Public contracts`
5. `## Dependencies`
6. `## Canonical documentation`

The code-local README must:

- identify the domain's runtime responsibility in concise terms;
- describe owned code areas at module/category level rather than enumerate every class;
- name intentional public/cross-domain contracts;
- name material upstream dependencies;
- link the canonical docs-domain root; and
- link material capability contracts when that improves developer navigation.

It must not contain stale ownership paths, historical implementation-phase narrative as the primary description, or claims that contradict the canonical domain contract.

## 4. Canonical domain README contract

Every `docs/domains/<domain>/README.md` must contain the metadata required by the repository documentation standard and these sections in order:

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
17. Capability documents.
18. Related documentation.

Each section must contain substantive current content or an explicit `Not applicable` explanation.

## 5. Required depth

A complete domain contract must make these questions answerable without reconstructing the domain from controllers and migrations:

- What does this domain own and explicitly not own?
- What are its important entities/value objects/state vocabularies?
- Which invariants must remain true?
- What are the important lifecycle transitions and who initiates them?
- Which permissions, assurance controls, tenant boundaries, and privacy rules apply?
- Which persistence belongs to this domain and which referenced data remains externally owned?
- Which contracts are consumed from or exposed to other domains?
- Which state is synchronous, asynchronous, append-oriented, idempotent, retryable, or concurrency-sensitive?
- What fails closed and what can be safely retried?
- What is intentionally not implemented?

Exact implementation details remain authoritative in code/tests; the contract documents stable semantics and ownership.

## 6. Capability split rule

A capability gets its own `docs/domains/<domain>/<capability>.md` when it is independently meaningful and at least one of the following is true:

- it has a distinct lifecycle/state machine or correction/history model;
- it has materially different authorization, privacy, or secret-handling requirements;
- it exposes or consumes a significant cross-domain contract;
- it has substantial idempotency/concurrency/failure behavior that deserves independent explanation;
- it owns a significant import/export/external-machine contract;
- it owns shared infrastructure consumed by multiple domains; or
- keeping its detailed contract only in the root makes the root difficult to navigate or obscures independent invariants.

Do **not** split merely because there are multiple models/controllers/tables. A coherent aggregate may remain in the root.

## 7. Capability document contract

Every living capability document must contain:

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** `<Domain>`

and these sections:

1. Purpose.
2. Scope and non-scope.
3. Model and state.
4. Invariants.
5. Workflows.
6. Authorization, tenancy and privacy.
7. Persistence and query semantics.
8. Events, integrations and background processing.
9. Failure, idempotency and concurrency.
10. Operations and observability.
11. Tests and validation.
12. Related documentation.

A capability file may summarize whole-domain rules by linking the root; it must not create a second ownership model.

## 8. Code-area inventory rule

Before a domain is marked complete for `DCP-P1`, its code must be reviewed at module/category level, including applicable:

- `Models/`;
- `Enums/`;
- `ValueObjects/`;
- `Actions/`;
- `Queries/`;
- `Services/`;
- `Policies/`;
- `Http/`;
- `Jobs/`;
- `Listeners/`; and
- console/scheduler-facing commands owned by the domain.

The coverage matrix records the resulting capability decision. The matrix is an inventory, not a substitute for the domain contract.

## 9. Cross-domain contract rule

Every material cross-domain relationship must be classified as one of:

- **consumes** — this domain depends on another domain's supported contract;
- **exposes** — this domain intentionally provides a contract another domain may use; or
- **reference only** — an identifier/model is referenced without transferring semantic ownership.

Documentation must not imply that referencing another domain's table/model transfers ownership.

Direct persistence reach-through that is not an intentional supported contract is an architecture/documentation defect to reconcile, not a contract to normalize silently.

## 10. State and history semantics

Where applicable, contracts must explicitly distinguish concepts that are easy to conflate, including:

- active versus archived/suspended/closed/deleted;
- missing versus recorded zero;
- current versus historical;
- mutable aggregate state versus append-only observation/evidence;
- retry of the same logical operation versus a new business transition; and
- neutral/global reference identity versus tenant-owned observations/workflows.

## 11. Non-capabilities

Every domain root must list explicit non-capabilities that prevent likely ownership drift. Important absence is part of the contract when a future contributor could reasonably infer the capability exists from adjacent code.

Examples include absence of write APIs, automated game ingestion, support impersonation, generic message transport, public tokens, or cross-tenant sharing.

## 12. Documentation ownership and evidence

Living contracts describe current runtime behavior. Product/security/operations evidence may support them, but accepted historical evidence does not replace a living contract.

A domain-specific acceptance/security/operations record belongs under the owning domain when it primarily describes that domain. Program-wide records remain in the shared top-level documentation groups.

## 13. P1 coverage inventory

`domain-coverage-matrix.md` is the authoritative `DCP-P1` inventory. It must contain one row for all 14 canonical domains and an explicit material-capability decision for each.

A domain may be `Complete` only when:

- the code-local README satisfies this standard;
- the canonical domain README satisfies this standard;
- every required capability document exists and satisfies this standard;
- code/documentation ownership is consistent;
- links/indexes are correct; and
- no required P1 defect remains hidden in a later phase.

Security/operations/interface topics that are deliberately deeper later-phase work may be recorded as later-phase inventory only after the domain contract states the applicable boundary at P1 depth.

## 14. CI enforcement

`DCP-P1` CI should enforce high-signal structural rules that can be derived deterministically:

- every code domain has a code-local README and canonical docs-domain README;
- code-local README required headings exist in order;
- canonical domain README required metadata/headings exist in order;
- living capability documents use the required metadata/headings;
- local Markdown links resolve; and
- domain indexes cannot name a capability file that does not exist.

CI does not attempt to judge prose quality. Coverage/content acceptance remains an explicit phase inventory review.

## 15. Definition of DCP-P1 complete

`DCP-P1` is complete only when:

- 14/14 code-local domain maps are complete;
- 14/14 canonical domain contracts are complete;
- 100% of material capability-document decisions in the frozen inventory are complete;
- no stale ownership/navigation statement remains in those artifacts;
- no orphan/duplicate domain document remains;
- the P1 validation record is complete; and
- protected checks pass on the exact candidate head.

The [Documentation completeness standard](documentation-completeness-standard.md) remains authoritative for the phase gate.
