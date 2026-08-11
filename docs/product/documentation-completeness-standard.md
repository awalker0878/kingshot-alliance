# Documentation completeness standard

[← Product and program documentation](README.md)

**Document type:** Normative documentation standard  
**Status:** Current  
**Applies to:** Documentation Completion Program phases, domain documentation, shared program documentation, and documentation-related CI gates  
**Repository:** `awalker0878/kingshot-alliance`

## 1. Purpose

This standard defines what **documentation complete** means and establishes the gate that controls movement between Documentation Completion Program phases.

A phase cannot advance because its major files exist or because most documentation is correct. It advances only when every required artifact and exit criterion for that phase is complete.

There is no `complete with follow-up` state for required phase scope. Work may be deferred only when the phase explicitly defines it as non-scope and records the owning later phase.

## 2. Completion levels

Completion is evaluated at three levels:

1. **Document complete** — one required document satisfies its applicable standard.
2. **Coverage complete** — every required subject/domain/interface/security/operations item in the phase inventory has complete documentation.
3. **Phase complete** — all required documents and coverage are complete, navigation and cross-references are correct, phase validation passes, and exit evidence is recorded.

A phase is incomplete if any lower level is incomplete.

## 3. Document status vocabulary

Documentation-program work uses these states:

- **Not started** — required artifact or coverage item has not been addressed.
- **In progress** — work exists but one or more completion criteria remain unsatisfied.
- **Blocked** — required completion depends on a concrete unresolved dependency.
- **Candidate** — content is believed complete and is awaiting the phase validation/exit gate.
- **Complete** — all applicable criteria passed and the artifact is accepted for the phase.

`Complete` is the only state that satisfies a phase exit gate.

## 4. Document-complete criteria

A required document is complete only when all applicable items are true:

- canonical owner and purpose are explicit;
- required metadata is present;
- all required sections from the applicable documentation standard are present;
- each section contains substantive current content or an explicit `Not applicable` explanation;
- no required content is left as `TODO`, `TBD`, placeholder, unresolved question, or implied future cleanup;
- code/domain ownership and authoritative source are unambiguous;
- authorization, tenancy, privacy, failure, idempotency, concurrency, security, operations, interfaces, and testing are addressed when applicable;
- cross-domain dependencies and exposed contracts are identified when applicable;
- living documentation describes current behavior rather than relying on historical implementation narrative;
- historical evidence is clearly distinguished from living contracts;
- all local Markdown links resolve;
- indexes/navigation expose the document from the correct owning area; and
- the document does not duplicate authoritative detail that belongs elsewhere.

## 5. Coverage-complete criteria

A phase inventory is coverage-complete only when:

- every in-scope code domain, capability, interface, security boundary, operating concern, evidence record, or governance subject has an explicit inventory row;
- every inventory row has an owner and status;
- every required row is `Complete`;
- there are no undocumented in-scope code ownership areas discovered during the phase;
- there are no orphan documents whose ownership cannot be determined;
- cross-domain/shared subjects are located in the correct shared program area rather than duplicated across domains; and
- deferred items identify the exact later phase that owns them and are genuinely outside the current phase's acceptance criteria.

Coverage is binary for the phase gate: 100% of required rows must be complete.

## 6. Phase-complete criteria

A Documentation Completion Program phase is complete only when:

1. its scope and inventory are frozen for the exit candidate;
2. all required artifacts are document-complete;
3. all required inventory rows are coverage-complete;
4. all standards introduced by the phase are current and indexed;
5. all affected repository navigation is current;
6. applicable source-of-truth and duplication checks are satisfied;
7. documentation architecture/link/structure tests pass;
8. any additional automated phase checks pass;
9. protected repository checks required by the phase pass on the candidate head; and
10. phase exit evidence records completed scope, validation, known non-scope, and the next phase.

If one criterion fails, the phase remains active.

## 7. No partial advancement

The following do **not** justify advancing a phase:

- most domains are documented;
- only low-risk gaps remain;
- a missing section seems obvious from code;
- a broken link has a known fix;
- a required standard is drafted but not adopted;
- CI is green while the documented phase inventory still has incomplete rows;
- a file exists but contains placeholders; or
- the missing material is expected to be documented in a later PR without being explicit non-scope.

Required scope is finished before advancement.

## 8. `continue` control rule

The user command **`continue`** is the control signal for the Documentation Completion Program.

On every `continue` request:

1. Read the current phase from `documentation-program-status.md`.
2. Read that phase's scope and exit criteria from `documentation-program-plan.md`.
3. Inspect the repository evidence needed to determine whether the current phase is actually complete.
4. If any required item is incomplete, remain in the current phase and finish the highest-priority incomplete items.
5. Re-run the phase's applicable completeness/validation checks.
6. Do not start the next phase while the current phase remains incomplete.
7. If every exit criterion is satisfied, record the current phase as `Complete`, advance the status ledger to the next phase, and begin that next phase.
8. If the final phase is complete, report the Documentation Completion Program as complete and switch future documentation work to normal maintenance governance.

Therefore `continue` always means exactly one of two things:

- **finish the current phase**, or
- **advance to and begin the next phase because the current phase is fully complete**.

It never means skip a gate.

## 9. Evidence expectations

Each phase exit should identify, as applicable:

- final repository/implementation SHA;
- documentation files created/updated/moved/retired;
- coverage inventory result;
- architecture/link/standard validation results;
- test/check evidence;
- explicit deferred/non-scope items; and
- next active phase.

Evidence may live in the phase status/exit record or an appropriate program audit/exit document, but the phase status must make the completion decision discoverable.

## 10. Relationship to other standards

This standard defines **when documentation work is complete**. Other documentation standards define **how particular documentation must be written and structured**.

The governing structure/ownership rules remain in [Repository documentation standard](documentation-standard.md). The phased program that introduces additional specialized standards is defined in [Documentation Completion Program](documentation-program-plan.md).
