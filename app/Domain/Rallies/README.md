# Rallies domain

## Purpose

Owns Player formations, Alliance Rally guidance, occurrence-specific recommended formations, Rally groups, Player assignments, assignment responses, and Rally participation evidence for Event operations.

## Owned code

Runtime code in this module owns Rally persistence, formation composition validation, exact Rally-Alliance resolution, Player eligibility, Rally actions/queries, and first-party Rally HTTP adapters.

## Public contracts

- `PlayerFormation` is owned by durable `player_id` and selected through authenticated Player Context.
- `RallyGuidanceRule` is reusable Alliance configuration.
- `EventRecommendedFormation` and `RallyGroup` are occurrence + operating-Alliance scoped.
- `RallyAssignment` identifies participants by durable `player_id`.
- Kingdom Events may contain independent Rally groups for multiple Alliances in the same occurrence.

## Dependencies

- `Events` — occurrence, capability, scope, target, participant and manager authorization.
- `Alliances` — Rally-operating Alliance context.
- `Kingdoms` — durable Player identity, Player Context and current Kingdom.
- `Authorization` — exact Alliance/Kingdom Event permissions.
- `Audit` / `Platform` — audit and scope-aware outbox evidence.

## Canonical documentation

- [`docs/domains/rallies/`](../../../docs/domains/rallies/README.md)
- [Events domain](../../../docs/domains/events/README.md)
- [Kingdoms domain](../../../docs/domains/kingdoms/README.md)
