# ADR 0009 — Separate versioned map truth from territory planning intent

Status: Accepted

Date: 2026-08-21

## Context

The Alliance Territory & Hive Planner requires KingShot map facts, placement rules, mutable Alliance/Kingdom layouts, deterministic analysis and later Event integration. Putting all of this into `BattlePlans`, `Alliance`, or a new top-level `Territory` context would mix distinct ownership and authority boundaries.

The application already defines `GameWorld` as the owner of neutral Kingdom/placement/reference facts and `Operations` as the owner of operational planning/execution state.

Community layout projects demonstrate useful features and may contain observed map coordinates/rules, but they are not automatically authoritative game data.

## Decision

1. Keep the existing seven top-level bounded contexts.
2. Add `GameWorld/KingdomMaps` as the owner of immutable/versioned map datasets, geometry facts, provenance and sourced placement rules.
3. Add `Operations/TerritoryPlanning` as the owner of mutable territory/hive plans, plan preferences, deterministic analysis, revisions and Operations-facing plan references.
4. Use `app/ReadModels/TerritoryPlanning` when the editor needs to compose map, Player, Alliance and planning reads.
5. Keep `Operations/BattlePlans` focused on Event objectives/assignments. It may reference a published territory-plan revision but does not persist spatial state.
6. Represent external Alliances/Governors as explicit plan-local references when no application identity exists.
7. Release map datasets immutably with schema version, observation/source metadata, confidence and checksum. Published plan revisions pin the dataset/checksum.
8. Treat browser geometry as preview and Laravel validation as authoritative; both implementations use shared golden fixtures.
9. Distinguish map facts, sourced game placement rules and planning preferences. Preferences generate warnings/suggestions, never fake game-rule violations.

## Consequences

### Positive

- map truth can evolve independently from saved plans;
- old published layouts remain reproducible after a map update;
- BattlePlans stays cohesive;
- no new top-level context is created for a single major feature;
- multi-Alliance planning does not force foreign entities into Alliance/GameWorld persistence;
- geometry and validation can be tested as explicit contracts;
- community-observed data can be useful without being mislabeled official.

### Costs

- editor reads cross context boundaries and therefore require explicit owner queries/read composition;
- PHP and TypeScript geometry parity requires shared fixtures and duplicate implementation of pure geometry behavior;
- map dataset lifecycle/provenance must be maintained explicitly.

## Revisit condition

Consider a new top-level Strategy/Planning context only if spatial planning grows into several independently governed capabilities with its own authority vocabulary and lifecycle, rather than promoting TerritoryPlanning merely because the editor becomes large.
