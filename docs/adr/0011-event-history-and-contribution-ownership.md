# ADR 0011 — Historical Event and contribution ownership

- **Status:** Accepted and implemented
- **Date:** 2026-08-14
- **Owners:** Events and Contributions domains
- **Related scope:** EVENT-CONTRIB-001 / EC-P0–EC-P7 and read surfaces
- **Supersedes:** None

## Context

KingShot activity spans Player-, Alliance-, and Kingdom-scoped Events. Players may change Alliances and Kingdoms over time, while current Alliance and Kingdom leaders still need authoritative access to the historical Events owned by the organization they currently lead. Players also need a lifetime history that follows durable `player_id` across those moves.

The existing Contributions model was initially Alliance-centric and materialized selected Events facts into Alliance-owned contribution records. That shape cannot cleanly represent Player-scoped Events, Kingdom-scoped Events, historical Alliance participation after a Player leaves, or Kingdom history after a Player transfers.

The database is greenfield for this redesign. Compatibility columns, dual-write periods, backfills, and legacy-authority shims are not required.

## Decision

Historical Event ownership is defined by three durable axes:

1. **Player history follows durable `player_id`.** Event participation/results for a Player remain part of that Player's history regardless of later Alliance or Kingdom changes.
2. **Alliance Event history belongs to the Event's immutable Alliance target.** A current authorized Alliance leader may view all historical Events targeted at that Alliance, including results for Players who later left.
3. **Kingdom Event history belongs to the Event's immutable Kingdom target.** A current authorized Kingdom leader may view all historical Events targeted at that Kingdom, including results for Players who later transferred Kingdoms.

Current authority determines who may read organizational history. Current membership or current Kingdom placement never determines whether a historical record belongs to an Event.

Events remains the source of truth for Event schedules, occurrences, participation, results, historical participation context, and Event metric facts. Contributions owns unified contribution/history reporting and composition across Event facts and non-Event contribution records; it must not duplicate Event facts into a second canonical ledger merely to support reporting.

Historical participation context may snapshot display/context data such as Player name, represented Alliance, or Kingdom at the time of an occurrence. Snapshot fields are historical presentation/evidence only and never authorize access.

Event metrics are comparable only within a compatible metric definition. The application must not sum unrelated scores such as Bear Hunt damage, Hall of Governors score, and Swordland battle score into an unexplained universal contribution score. Cross-Event rollups may aggregate universally meaningful facts such as participation, completed/absent/excused outcomes, and reliability, while Event-specific metrics remain typed by Event Type scope and metric key.

Event target identity becomes immutable after Event creation. Historical Event/result persistence must not be cascade-deleted merely because a Player leaves, an Alliance lifecycle changes, or a Player transfers Kingdoms.

## Implemented boundaries

The implementation now enforces the decision through these canonical paths:

- `EventPlayerContextFreezer` freezes occurrence-time Player context exactly once at an evidence-bearing participation/result boundary.
- `EventMetricCapture` validates normalized Event metrics against their exact Event Type scope, subject, value type, and occurrence dimension.
- `EventPlayerHistoryQuery` starts from exact `event_player_contexts.player_id` and never aggregates sibling Players owned by one User.
- `EventAllianceHistoryQuery` and `EventKingdomHistoryQuery` authorize with the current active Player and then read from immutable Event targets.
- `EventOrganizationHistoryQuery` never uses current roster/current Kingdom placement to decide historical ownership.
- `PlayerContributionHistoryQuery` composes Events-owned facts with genuine Contributions-owned records at read time; it does not copy Event results into `contribution_records`.
- `/contributions/history` is intentionally independent of `alliance.context` so a Player keeps personal history after leaving an Alliance.
- `/alliances/{alliance}/events/history` and `/kingdoms/{kingdom}/events/history` use current Player scope authority to gate organization history.

## Consequences

### Positive

- Player history survives Alliance and Kingdom movement.
- New Alliance leadership inherits complete Alliance-owned Event history without depending on current roster membership.
- New Kingdom leadership inherits complete Kingdom-owned Event history without depending on current Player Kingdom placement.
- Former leaders lose organization-wide access when current authority is lost while retaining their own Player history.
- Events remains the canonical operational source, avoiding reconciliation drift between Event facts and contribution reporting.
- Greenfield schema can enforce the final model directly with immutable targets, normalized metrics, and durable Player identity.

### Negative or trade-off

- Contributions reporting must compose data across domain-owned sources instead of reading one Alliance-only ledger.
- Historical context requires explicit snapshot fields where current names/affiliations would otherwise rewrite the past.
- Metric normalization requires a catalogue and typed values rather than unconstrained JSON-only analytics.
- Organizational history queries must distinguish current authorization from historical ownership.

## Supported boundaries affected

- **Events** owns historical Event targets, occurrences, participation/result facts, Event metric definitions/values, and occurrence-time Player context.
- **Contributions** owns unified contribution/history queries, reporting, exports, and non-Event contribution records; it consumes Events facts without becoming their owner.
- **Memberships** supplies current Alliance authority context but is not historical Event identity.
- **Kingdoms** owns durable Player/Kingdom identity and current Kingdom role authority; current placement does not rewrite Event history.
- **Authorization** evaluates current Player/Alliance/Kingdom read/manage authority against the immutable Event target.
- **Platform Admin** remains User-scoped and gains no game-domain Event-history bypass.

See [Events](../domains/events/README.md), [Event contribution history](../domains/events/event-contribution-history.md), [Contributions](../domains/contributions/README.md), and [ADR 0010](0010-transactional-mutation-authority.md).

## Validation

Implementation and tests prove:

- a Player keeps personal Event history after leaving an Alliance or transferring Kingdoms;
- a current Alliance leader can read all historical Events targeted at that Alliance, including former-member results;
- a current Kingdom leader can read all historical Events targeted at that Kingdom, including transferred-Player results;
- a former leader loses organization-wide history access when current authority is lost;
- sibling Players owned by the same User never share history implicitly;
- current Alliance membership/current Kingdom placement is not used to filter historical Event participants;
- historical context snapshots never grant authorization;
- Event targets cannot change after creation;
- Event metrics and phase/objective dimensions are validated at capture;
- Event and Contribution facts remain separate canonical ledgers and are composed only at read time; and
- incompatible Event metrics are never silently combined into a universal score.

## Revisit when

Revisit only if KingShot introduces a materially different historical owner beyond Player, Alliance, or Kingdom, or if a future normalized cross-Event score is deliberately specified with transparent normalization semantics and governance.

## Supersession handling

None.
