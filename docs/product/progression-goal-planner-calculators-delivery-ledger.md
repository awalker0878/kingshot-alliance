# Progression Goal Planner and Calculator Evidence Program — Delivery Ledger

Status: Active reconciliation — 2026-08-26

Source of truth: [Progression Goal Planner and Calculator Evidence Program](progression-goal-planner-calculators.md).

This ledger records implementation state separately from the contract-start snapshot retained in the source-of-truth document. A family whose evidence gate fails is complete only when the failed/incomplete disposition is inspectable, machine-readable, tested, and impossible to bypass.

| Item | Required outcome | Current status | Evidence |
| --- | --- | --- | --- |
| PG-01 | Canonical product/ownership/authorization/provenance contract | Complete | `docs/product/progression-goal-planner-calculators.md` |
| PG-02 | Dataset-pinned progression topology/state query | Complete | `ProgressionTopologyQuery`; factual-only comparison tests |
| PG-03 | Authorized observed-current composition | Complete | `ProgressionPlannerController`; `ProgressionPlannerQuery`; architecture authorization-order test |
| PG-04 | Target/path/prerequisite planner response | Complete for sourced deterministic topology | Targets/path are dataset-backed; Academy source gaps produce no synthesized states; prerequisite strings remain sourced and unknown when satisfaction cannot be observed |
| PG-05 | Planner UX and required states | Complete | `Kingdom/Progression/Planner.vue`; unknown, unresolved, invalid/reverse, evidence status, dataset mismatch, calculation result states |
| PG-06 | Planner acceptance/localization/accessibility/visual tests | Implemented — repository verification pending | Query/unit tests, English/French domain labels with normal locale fallback, semantic form labels/ARIA, Playwright desktop/mobile coverage |
| CE-GEAR | Governor Gear evidence qualification report + machine-readable eligibility | Complete — qualified | Tier-A Century Games/KingShot Official Wiki canonical 58-row table; superseded community conflict retained; report `2026.08.23.2.json` |
| CE-CHARM | Governor Charm evidence qualification report + machine-readable eligibility | Complete — qualified | Tier-A official 22-level table + maintained independent corroboration; historical max-level conflict retained |
| CE-HERO-GEAR | Hero Gear/Mastery evidence qualification report | Complete — evidence incomplete | Missing complete independent calculator evidence/transition semantics; calculator disabled |
| CE-TROOPS | Troop training/promotion evidence qualification report | Complete — evidence incomplete | Training-vs-promotion/modifier boundaries and independent calculator evidence incomplete; calculator disabled |
| CE-RESEARCH | Academy/War Academy evidence qualification report | Complete — source gap | Academy `Fortified Mail VI` table gap plus calculator-unit/evidence gaps; calculator disabled |
| CE-BUILDINGS | Buildings/Truegold evidence qualification report | Complete — evidence conflict | Maintained prerequisite conflict + incomplete independent calculator evidence; calculator disabled |
| CI-GEAR | Governor Gear pure calculator + golden fixtures + UI | Complete — qualified family | `ProgressionCalculator` `governor-gear-v1`; exact one-step/multi-step/same/reverse fixtures; UI only consumes typed result |
| CI-CHARM | Governor Charm pure calculator + golden fixtures + UI | Complete — qualified family | `ProgressionCalculator` `governor-charms-v1`; explicit level-zero boundary; exact one-step/multi-step/reverse fixtures |
| CI-HERO-GEAR | Hero Gear/Mastery calculator | Correctly unavailable | Evidence gate failed; no runtime calculator implementation or UI action is exposed |
| CI-TROOPS | Troop calculator | Correctly unavailable | Evidence gate failed; no runtime calculator implementation or UI action is exposed |
| CI-RESEARCH | Research calculator | Correctly unavailable | Source gap/evidence gate failed; no runtime calculator implementation or UI action is exposed |
| CI-BUILDINGS | Building calculator | Correctly unavailable | Evidence conflict/evidence gate failed; no runtime calculator implementation or UI action is exposed |
| PG-07 | Documentation/implementation reconciliation | Complete for current candidate | Product contract, current ledger and architecture context map agree with implementation |
| PG-08 | Full repository verification and immutable closeout candidate | In progress | Draft PR #129; CI/Architecture/Intelligence/CodeQL/Visual/Dependency workflows run on every candidate SHA |

## Evidence-gate disposition

The current immutable factual release is `kingshot-2026-08-23-v2` / dataset `2026.08.23.2`.

```text
Governor Gear       calculator_ready
Governor Charms     calculator_ready
Hero Gear/Mastery   evidence_incomplete
Troop Training      evidence_incomplete
Research            source_gap
Buildings/Truegold  evidence_conflict
```

There is no global calculator switch. `CalculatorEligibilityQuery` loads the qualification report for the selected release, validates its registered source IDs and gate types, computes the qualification-report SHA-256, and returns status independently for each family.

Historical `kingshot-2026-08-23-v1` remains usable for factual planning. Because no calculator qualification report exists for that historical release, its calculator status is `evidence_review` with blocker `qualification_report_missing`; the newer calculator implementations are not reused against it.

## Closeout condition

PG-08 may move to **Complete** only when one final immutable branch SHA has all required repository workflows green and the PR diff has been reconciled back to these product/architecture documents. If a verification failure changes product behavior or reveals another requirement, update the product contract/ledger first, implement the correction, and rerun the gates.
