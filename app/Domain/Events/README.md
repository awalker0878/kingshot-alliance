# Events domain

## Purpose

Owns KingShot Event types, scoped scheduling, participation, coordination, battle planning, results, and Event-facing authorization across Player, Alliance, and Kingdom contexts.

## Owned code

Runtime code in this module owns Event scope/capability vocabulary, the Event Type catalogue, Event authorization composition, creation-context discovery, scheduling, participation, occurrence phases, polls, Player voting, hierarchical rosters, Player assignment/confirmation, hierarchical battle objectives and assignments, occurrence and Player results, same-target Player intelligence, Event queries, active-Player Event visibility, bounded attention/calendar read models, localization keys, and first-party Event surfaces.

## Public contracts

- `EventScope` — `player`, `alliance`, `kingdom`.
- `EventCapability` — reusable operational-module vocabulary.
- `EventAuthorization` — exact scope/target permission composition.
- `EventCreationContextResolver` — Event targets where the actor may create.
- `EventVisibilityResolver` / `ActivePlayerEventVisibilityResolver` — bounded User visibility and active-Player eligible target sets.
- Event Type catalogue — supported scopes, defaults, capabilities, localization keys, and presentation metadata.
- Occurrence phases and polls — capability-driven timelines, options, active-Player voting, and vote read models.
- Event rosters — occurrence-scoped hierarchy, durable Player assignments, availability warnings, and self confirmation.
- Battle plans — occurrence-scoped objective hierarchy, priority/status, and exact Player-or-roster assignments.
- Results and Player intelligence — occurrence summaries, durable Player results, and same-target planning metrics.

## Dependencies

- `Authorization` — Alliance and Kingdom permission grants.
- `Memberships` / `Alliances` — active Alliance and rank context.
- `Kingdoms` — durable `Player` and `Kingdom` targets.
- `Notifications`, `Rallies`, `Audit`, and `Platform` — Event operations, durable evidence, and platform administration.

## Canonical documentation

- [`docs/domains/events/`](../../../docs/domains/events/README.md)
- [EVENTS-002 implementation plan](../../../docs/domains/events/product/events-002-scoped-event-operations-implementation-plan.md)
