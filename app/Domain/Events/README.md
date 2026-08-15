# Events domain

## Purpose

Owns KingShot Event types, scoped scheduling, participation, coordination, battle planning, results, normalized Event metrics, and durable historical Event facts across Player, Alliance, and Kingdom contexts.

## Owned code

Runtime code in this module owns Event scope/capability vocabulary, the Event Type catalogue, Event authorization composition, creation-context discovery, scheduling, participation, occurrence phases, polls, Player voting, hierarchical rosters, Player assignment/confirmation, hierarchical battle objectives and assignments, occurrence/Alliance/Player results, Event metrics, occurrence-time historical Player context, Event intelligence, Event queries, active-Player Event visibility, bounded attention/calendar/history read models, localization keys, and first-party Event surfaces.

## Public contracts

- `EventScope` — exact `player`, `alliance`, or `kingdom` historical owner type.
- Event target — immutable Player, Alliance, or Kingdom owner after creation.
- `EventCapability` — reusable operational-module vocabulary.
- `EventAuthorization` / `EventMutationAuthority` — exact current scope/target authority composition.
- `EventCreationContextResolver` — Event targets where the actor may create.
- `EventVisibilityResolver` / `ActivePlayerEventVisibilityResolver` — current active-Player eligible target sets.
- Event Type catalogue — supported scopes, defaults, capabilities, localization keys, and presentation metadata.
- Occurrence phases and polls — capability-driven timelines, options, active-Player voting, and vote read models.
- Event rosters — occurrence-scoped hierarchy, durable Player assignments, availability warnings, and self confirmation.
- Battle plans — occurrence-scoped objective hierarchy, priority/status, and exact Player-or-roster assignments.
- Results, metrics, and Player intelligence — occurrence summaries, durable Player results, compatible metrics, and historical intelligence.
- Historical ownership — Player history follows durable `player_id`; Alliance/Kingdom history follows the immutable Event target rather than current membership/placement.

## Dependencies

- `Authorization` — current Alliance and Kingdom permission grants.
- `Memberships` / `Alliances` — current Alliance authority/eligibility context only; membership is not historical Event identity.
- `Kingdoms` — durable `Player` and `Kingdom` targets/current Kingdom authority context.
- `Notifications`, `Rallies`, `Audit`, and `Platform` — Event operations, durable evidence, and platform infrastructure.
- `Contributions` consumes supported Events facts for unified history/reporting without taking Event fact ownership.

## Canonical documentation

- [`docs/domains/events/`](../../../docs/domains/events/README.md)
- [Event contribution and historical intelligence](../../../docs/domains/events/event-contribution-history.md)
- [EVENT-CONTRIB-001 implementation plan](../../../docs/domains/events/product/event-contribution-history-implementation-plan.md)
- [EVENTS-002 implementation plan](../../../docs/domains/events/product/events-002-scoped-event-operations-implementation-plan.md)
