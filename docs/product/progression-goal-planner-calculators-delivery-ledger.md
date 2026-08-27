# Progression Goal Planner and Calculator Evidence Program — Delivery Ledger

Status: Complete when the exact containing commit is green across all required repository workflows — 2026-08-27

Source of truth: [Progression Goal Planner and Calculator Evidence Program](progression-goal-planner-calculators.md), with verification-specific reconciliation in [Progression Goal Planner and Calculator Evidence Program — Verification Reconciliation Amendment](progression-goal-planner-calculators-verification-amendment.md).

This ledger records implementation state separately from the contract-start snapshot retained in the source-of-truth document. A family whose evidence gate fails is complete only when the failed/incomplete disposition is inspectable, machine-readable, tested, and impossible to bypass. Any required workflow failure on the commit containing this ledger reopens the affected delivery item and PG-08 until resolved.

The immediately preceding implementation candidate `5a637377925c1fc3e4aaa95f2acca8e909641c45` passed CI, Architecture V3 Verification, Intelligence Verification, CodeQL, Visual Regression and Dependency Review after the verification amendments and reviewed visual fingerprints were committed. The exact commit containing this reconciled ledger must pass those same six workflows; when it does, PG-08 and this ledger are complete without another content change. If any required workflow fails, PG-08 is reopened and this document must be reconciled before implementation changes.

| Item | Required outcome | Current status | Evidence |
| --- | --- | --- | --- |
| PG-01 | Canonical product/ownership/authorization/provenance contract | Complete | `docs/product/progression-goal-planner-calculators.md` plus verification amendment |
| PG-02 | Dataset-pinned progression topology/state query | Complete | `ProgressionTopologyQuery`; factual-only comparison tests |
| PG-03 | Authorized observed-current composition | Complete | `ProgressionPlannerController`; `ProgressionPlannerQuery`; architecture authorization-order test |
| PG-04 | Target/path/prerequisite planner response | Complete for sourced deterministic topology | Targets/path are dataset-backed; Academy source gaps produce no synthesized states; direct source prerequisites remain sourced and satisfaction remains `unknown` unless canonical identities permit deterministic resolution |
| PG-05 | Planner UX and required states | Complete | Canonical Governor entry, explicit stale/no-dataset states, factual path/target, prerequisite boundary, dataset mismatch, calculator status/result, responsive/localized presentation |
| PG-05A | Governor Progression exposes the canonical Goal Planner entry point | Complete | `Kingdom/Progression/Governor.vue` exposes the localized planner action; architecture/visual coverage protects the canonical handoff |
| PG-05B | Observed current state exposes configured stale/fresh presentation semantics without changing factual truth | Complete | `ProgressionPlannerQuery` derives freshness from `capturedAt` using `config/intelligence.php`; boundary tests prove staleness is presentation-only |
| PG-05C | No-dataset and prerequisite-boundary UX are explicit and non-speculative | Complete | Planner has an explicit read-only `no_dataset` surface; direct source-text prerequisites remain `unknown` unless canonical identities support deterministic resolution |
| PG-05D | Dataset absence is distinguished from dataset integrity failure | Complete | `NoProgressionDatasetPublished` is the only absence signal handled as `no_dataset`; schema/read/required-file failures remain hard failures; permanent architecture test covers the boundary |
| PG-06 | Planner acceptance/localization/accessibility/visual tests | Complete | Pinned-dataset factual target assertion, reviewed Governor fingerprints, semantic Transfer evidence-card assertions, reviewed Transfer desktop/mobile fingerprints, responsive/overflow coverage, query/unit/architecture coverage |
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
| PG-07 | Documentation/implementation reconciliation | Complete | Entry-point, freshness, prerequisite identity, no-dataset/integrity, pinned visual truth and repository-wide visual integration requirements were documented before their corresponding fixes and are represented in the source-of-truth set |
| PG-08 | Full repository verification and immutable closeout candidate | Complete when the exact containing SHA has all six required workflows green | Pre-closeout candidate `5a637377925c1fc3e4aaa95f2acca8e909641c45` passed all six. This containing commit must independently pass CI, Architecture V3 Verification, Intelligence Verification, CodeQL, Visual Regression and Dependency Review; a failure automatically reopens this row |

## Reconciliation requirements discovered during verification

### PG-05A — canonical Governor entry point

The Goal Planner is part of the Governor Progression experience, not an isolated URL. `Kingdom/Progression/Governor.vue` exposes a localized, keyboard-accessible `Goal planner` action to `/progression/governor/planner`. The planner provides a return path to Governor Progression. Navigation does not imply that opening the planner writes or changes observed state.

### PG-05B — observation freshness presentation

The planner preserves immutable observation provenance while exposing configured freshness presentation. For an observed current state:

- `fresh` means the newest applicable fact capture is within the configured threshold;
- `stale_observation` means it is older than the threshold;
- stale observations remain factual observations and retain their original source/date/dataset pins;
- stale status never changes GameWorld truth, never fabricates a newer value, and never by itself enables or disables a calculator;
- the planner UX visibly discloses stale status and capture date;
- the threshold is tested at the boundary and is not embedded in Vue.

The default presentation threshold is **30 days**, configurable through `config/intelligence.php`. This is an informational freshness policy, not an evidence-retention or authorization policy.

### PG-05C — no dataset and prerequisite identity boundary

If no factual progression release is published, the Planner renders an explicit no-dataset state and stops before Alliance/Roster observation retrieval or calculation. It does not synthesize an empty release. Research prerequisites in the current release are source text rather than canonical edge identities; the Planner therefore displays those direct facts and keeps observed satisfaction `unknown`. Transitive prerequisite traversal is not inferred from names/prose and becomes eligible only when a future immutable dataset publishes canonical prerequisite identities/edges.

### PG-05D — dataset absence versus integrity failure

The read model renders `no_dataset` only when no factual progression release exists. `ProgressionDatasetQuery` emits the dedicated `NoProgressionDatasetPublished` exception for true absence, and the Planner catches only that signal. A published release that cannot be read or validated is an integrity failure, not an empty catalogue: invalid JSON, unsupported schema, missing required files, checksum mismatches and other release-validation failures remain hard failures. This prevents corruption from being presented as ordinary product unavailability.

### PG-06A — pinned-dataset visual truth

The first full visual run exposed a stale test assertion rather than a product defect: `target=step:3` resolves to `Blue ★1` in immutable release `2026.08.23.2`, while the test asserted nonexistent `Green ★3`. Visual fixtures now assert the factual state returned by the pinned dataset. The same reconciliation reviewed and locked the intentional Governor Progression visual change created by the canonical Goal Planner entry point.

### PG-08A — repository-wide visual integration

The final Visual Regression gate also exercises Kingdom Transfer. Screenshot Intake evolution on the exact `main` merge base legitimately added nested review content, so generic descendant `<article>` counting and cross-card first-match selectors were replaced with semantic nearest-card assertions. The Score/Passes preview is asserted by its complete factual payload rather than an assumed standalone text prefix. The rendered desktop/mobile surfaces were reviewed and locked to:

- desktop `bc54da5d6100313fbfd6b82ab547cdf4f97fb845c6a52679fcc315ef7d62f4fc`;
- mobile `1698876e5bd830fd5ef221ef2990664c7f70794c424edfe2cbd2ca9fb3dce12c`.

No Kingdom Transfer product behavior or calculator evidence status was changed by this verification repair.

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

The ledger is complete only while the exact commit containing it has CI, Architecture V3 Verification, Intelligence Verification, CodeQL, Visual Regression and Dependency Review green and the PR diff remains reconciled to the product/architecture contract. A workflow failure, product mismatch or newly discovered requirement automatically reopens the affected item and PG-08, requires documentation-first reconciliation, and must be resolved before the capability is considered complete.
