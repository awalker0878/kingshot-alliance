# Progression Goal Planner and Calculator Evidence Program — Verification Reconciliation Amendment

Status: Implementation source-of-truth amendment — 2026-08-27

This amendment is additive to `progression-goal-planner-calculators.md` and its delivery ledger. It records verification requirements discovered only after the complete implementation was exercised against the repository-wide pull-request gates from the exact current `main` merge base (`2716cc8911c245609131d05bb5a94c206d50b61d`). Where this amendment is more specific about verification behavior, it governs the closeout candidate.

## Why PG-08 is reopened

The first immutable closeout candidate passed CI, Architecture V3 Verification, Intelligence Verification, CodeQL and Dependency Review, but failed Visual Regression. Per the delivery contract, that failure reopens PG-08 and any visual-coverage item affected by the failure. The capability is not complete again until all required workflows pass on one later immutable containing commit.

## Visual fixture truth must come from the pinned dataset

Visual and acceptance fixtures must assert the canonical state selected from the same pinned immutable dataset that the UI is rendering. A test must not assert an invented label, infer a label from an ordinal, or use a stale expectation that contradicts the release rows.

For dataset `kingshot-2026-08-23-v2` / version `2026.08.23.2`, Governor Gear state ID `step:3` is the fourth `upgradeSteps` row and therefore resolves to **Blue ★1**. A fixture using `target=step:3` must assert `Blue ★1`; `Green ★3` is not a state in that release and must never be introduced merely to satisfy a test.

If a visual fixture needs another target, it must select that target by a state ID actually returned by `ProgressionTopologyQuery` for the pinned release and assert the returned factual label.

## Intentional Governor Progression visual change

The Governor Progression surface now exposes the canonical localized `Progression Goal Planner` entry point required by PG-05A. That intentional product change alters the Governor visual fingerprint on both desktop and mobile. A baseline/fingerprint may be updated only after reviewing the rendered failure evidence and confirming that:

- the Goal Planner entry point is present and usable;
- existing Observation history and Saved loadouts semantics remain visible;
- the no-observation state remains explicit;
- no horizontal overflow or unrelated visual regression is introduced.

The reviewed rendered evidence satisfies those conditions; the new stable fingerprints may therefore replace the pre-planner fingerprints.

## Repository-wide visual gate integration

PG-08 is a repository-wide verification requirement, not a progression-only test selection. If an unrelated visual test present on the exact `main` merge base fails while exercising the final candidate, it must be resolved before closeout when the failure is a verification defect rather than a product-contract conflict.

The Kingdom Transfer visual suite currently counts every descendant `<article>` inside the `Add in-game evidence` details region. Screenshot Intake evolution on `main` legitimately added nested article-shaped review content, so the descendant count is no longer a stable assertion of the three evidence-class scenarios the test intends to protect. The test must scope those three scenarios semantically by their distinct headings or a purpose-built test ID rather than by generic descendant element count. This repair changes no Kingdom Transfer product semantics.

The same suite also contains `*_PENDING` visual fingerprint placeholders. Those placeholders are not an acceptable green closeout state. After the semantic selector is repaired and the actual rendered page is reviewed, the captured desktop/mobile fingerprints must replace the placeholders and remain protected by the normal visual workflow.

## Verification sequence

1. Reconcile this amendment before test changes.
2. Correct the Progression visual target assertion to match the pinned dataset and update only reviewed intentional Governor fingerprints.
3. Repair the Kingdom Transfer visual selector to assert the intended evidence scenarios semantically.
4. Run Visual Regression to obtain/review the now-reachable Kingdom Transfer final fingerprints and replace any pending placeholders.
5. Run the repository-required workflows against one immutable final SHA containing the implementation, this reconciliation, final visual assertions/fingerprints and the reconciled delivery ledger.
6. PG-08 may return to `Complete` only when CI, Architecture V3 Verification, Intelligence Verification, CodeQL, Visual Regression and Dependency Review are all green on that same SHA.

## Evidence-gate invariants remain unchanged

This amendment does not qualify any additional calculator family and does not relax any evidence requirement. The current family dispositions remain:

```text
Governor Gear       calculator_ready
Governor Charms     calculator_ready
Hero Gear/Mastery   evidence_incomplete
Troop Training      evidence_incomplete
Research            source_gap
Buildings/Truegold  evidence_conflict
```

No calculator may be enabled because of a visual or CI reconciliation change. Evidence qualification, immutable dataset/checksum binding, conflict representation and golden fixture requirements continue to govern independently per family.
