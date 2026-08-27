# Progression Goal Planner and Calculator Evidence Program — Delivery Ledger

Status: Verification reopened after Visual Regression failure — 2026-08-27

Source of truth: [Progression Goal Planner and Calculator Evidence Program](progression-goal-planner-calculators.md), with verification-specific reconciliation in [Progression Goal Planner and Calculator Evidence Program — Verification Reconciliation Amendment](progression-goal-planner-calculators-verification-amendment.md).

This ledger records implementation state separately from the contract-start snapshot retained in the source-of-truth document. A family whose evidence gate fails is complete only when the failed/incomplete disposition is inspectable, machine-readable, tested, and impossible to bypass. Any required workflow failure on the commit containing this ledger reopens the affected delivery item and PG-08 until resolved.

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
| PG-06 | Planner acceptance/localization/accessibility/visual tests | Reopened — visual fixture reconciliation in progress | First containing commit proved target assertion/baseline drift; amendment requires pinned-dataset-consistent target assertion and reviewed Governor fingerprints before closeout |
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
| PG-07 | Documentation/implementation reconciliation | Reopened for verification blockers | Visual fixture truth and repository-wide integration requirements are now documented in the verification amendment before test changes |
| PG-08 | Full repository verification and immutable closeout candidate | Reopened — Visual Regression failed on `b5026f58…` | CI, Architecture V3 Verification, Intelligence Verification, CodeQL and Dependency Review passed on `b5026f58…`; Visual Regression failed and must be corrected. All six workflows must be green on one later exact ledger-containing SHA |

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

The first full visual run exposed a stale test assertion rather than a product defect: `target=step:3` resolves to `Blue ★1` in immutable release `2026.08.23.2`, while the test asserted nonexistent `Green ★3`. Visual fixtures must assert the factual state returned by the pinned dataset. The same run confirmed the intentional Governor Progression visual change created by the canonical Goal Planner entry point; only reviewed desktop/mobile fingerprints may replace the old baselines.

### PG-08A — repository-wide visual integration

The final Visual Regression gate also exercises Kingdom Transfer. The exact `main` merge base contains Screenshot Intake evolution that legitimately adds nested review articles inside `Add in-game evidence`; therefore a generic count of every descendant `<article>` no longer represents the three intended evidence scenarios. The test must scope the scenarios semantically, and its pending visual fingerprint placeholders must be replaced with reviewed captured fingerprints before PG-08 can close. No Kingdom Transfer product behavior is changed by this verification repair.

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

This ledger is complete only while the exact commit containing it has all required repository workflows green and the PR diff remains reconciled to the product/architecture contract. A workflow failure, product mismatch or newly discovered requirement reopens the affected item and PG-08, requires documentation-first reconciliation, and must be resolved before the capability is considered complete.
