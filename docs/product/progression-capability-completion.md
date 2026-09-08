# Progression Capability Completion

Status: Active implementation contract — 2026-09-08

## Outcome

Progression is complete when KingShot factual progression truth can be safely observed, compared and planned without collapsing catalogue truth, Governor observations, tactical intent or evidence confidence into one model.

The capability follows the flow:

```text
reviewed source snapshots
  -> immutable GameWorld/Progression release
  -> typed factual topology and prerequisite graph

reviewed Governor evidence
  -> append-only Intelligence/Roster observations
  -> authorized current-state projection

factual topology + authorized observation
  -> ReadModels/Progression comparison
  -> prerequisite evaluation
  -> evidence-qualified calculation where permitted
```

## Ownership

### GameWorld / Progression

Owns immutable dataset releases, source manifests, factual entities and state ladders, prerequisite graphs, source conflicts/gaps, calculator qualification and pure dataset-pinned calculation rules.

It does not own Governor observations, Alliance authorization, screenshot evidence or user tactical intent.

### Intelligence / Roster

Owns append-only Governor progression observations and the current projection derived from them. Every observed fact retains its capture time and progression dataset ID/checksum.

### Intelligence / Evidence

Owns screenshot intake, extraction, review, duplicate handling and the normalization handoff. Unsupported evidence must fail closed.

### ReadModels / Progression

Composes factual Progression data with authorized observations. It owns no persistence.

## Fresh-deployment rules

This deployment has no compatibility obligation. Do not add legacy shims, dual-write paths or schema compatibility translations. Prefer the clean current contract and remove stale/dead surfaces when superseded.

## Release contract

Each immutable release declares its own machine-readable coverage assertions. Runtime code validates structural invariants generically; release-specific counts belong to the release manifest, not PHP constants.

Published release states are:

- `candidate`
- `reviewed`
- `published`
- `superseded`
- `rejected`

`latest()` selects only `published` releases. Historical releases remain directly loadable by ID/checksum.

## Observation families

The current-state projection supports, where reviewed evidence exists:

- Governor profile;
- Heroes, level, stars/substars and exclusive equipment/Widget level;
- Hero Gear and Mastery;
- Governor Gear;
- Governor Charms;
- Buildings;
- Academy Research;
- War Academy Research;
- troop tiers;
- Pets;
- Masters;
- VIP progression.

Missing facts remain unknown. A partial screenshot never implies absence unless the evidence schema explicitly represents a complete capture.

## Planner families

The Goal Planner must support deterministic factual topology for:

- Governor Gear;
- Governor Charms;
- Hero level;
- Hero stars/substars;
- Hero exclusive equipment / Widgets;
- Hero Gear level;
- Hero Gear Mastery;
- Buildings;
- Academy Research;
- War Academy Research;
- troop tiers;
- Pets;
- Masters;
- VIP progression.

A planner family is exposed only when state identity and order/graph semantics are deterministic in the pinned release.

## Prerequisite evaluation

Planner prerequisites use explicit statuses:

- `satisfied`
- `not_satisfied`
- `unknown_current_state`
- `dataset_mismatch`
- `unobservable`
- `source_conflict`
- `unsupported`

Unknown is never coerced to false. Source-conflicted requirements cannot silently pass.

## Calculator boundary

Factual catalogue coverage does not authorize a calculator. Calculator families remain evidence-gated. A calculation requires source/version/unit completeness, explicit conflict handling, immutable dataset pinning, typed transition semantics, golden fixtures and result provenance.

Governor Gear and Governor Charms remain the initial qualified families. Other families remain unavailable until their qualification manifest passes.

## Operational lifecycle

Source refresh produces a new candidate release, a semantic change report and validation output. Runtime requests never scrape third-party sources. Publication is review-gated and never overwrites a historical release directory.

## Completion gates

Progression is complete only when:

1. every documented factual family has an explicit disposition;
2. release-specific integrity expectations live in release manifests;
3. supported observation families are append-only and dataset-pinned;
4. planner families map observed state to factual topology when observation exists;
5. prerequisites are evaluated when observable and remain explicit otherwise;
6. calculators are implemented only when evidence-qualified;
7. the Library handles no-dataset/integrity states deliberately;
8. the refresh pipeline is release-generic and review-gated;
9. operational diagnostics expose release/source age, gaps, conflicts and calculator blockers;
10. V3 architecture, domain, authorization, frontend and visual coverage pass.
