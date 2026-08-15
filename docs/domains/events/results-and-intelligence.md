# Event results and Player intelligence

[← Events domain](README.md)

**Document type:** Living capability contract
**Status:** Current
**Owning domain:** Events

## 1. Purpose

Defines occurrence-level results, durable Player results, normalized Event metrics, and planning intelligence for Event Type scopes that enable the `results` capability.

## 2. Scope and non-scope

In scope:

- one summary result for an Event occurrence;
- one result row per `(occurrence_id, player_id)`;
- optional Alliance result rows inside Kingdom Events where configured;
- score, rank, outcome, notes, and normalized metric values;
- manager result entry and correction;
- active-Player result display;
- durable cross-Alliance/cross-Kingdom Player history;
- organization-owned historical intelligence by immutable Event target; and
- reliability derived from explicit participation outcomes.

Out of scope: automated game telemetry ingestion unless separately integrated, subjective Player ratings, authorization derived from performance, aggregation by User account, and an unexplained universal score across incompatible Event metrics.

## 3. Identity and model

`EventResult` is occurrence-level operational history. `EventPlayerResult` is Player-level operational history and is keyed by durable `player_id`.

`players.user_id` is authoritative Player ownership only. A single User may own any number of Player rows. Those Players keep independent Event result and intelligence histories even when they share the same `user_id`.

Result subject and recorder are separate concepts:

- `player_id` identifies the Player whose result is being recorded; and
- `recorded_by_player_id` identifies the active Player persona that performed a game-domain mutation, or is null for an explicitly system-originated mutation.

Alliance membership is never result identity.

## 4. Invariants

1. At most one `EventResult` exists per occurrence.
2. At most one `EventPlayerResult` exists per occurrence and Player.
3. Player results may be recorded only for a Player eligible for the exact Event target at the time of the occurrence/workflow.
4. Result persistence never uses Alliance membership as Player identity.
5. Player result history survives Alliance movement and Kingdom transfer because it is keyed by durable Player identity.
6. Multiple Players owned by one User never share or merge result rows.
7. Event scope/target identity is immutable and defines organization-owned history.
8. Current organization authority determines who may read Alliance/Kingdom-wide history; current membership/current Kingdom placement does not filter historical Event participants.
9. Event result writes require the exact Event manage permission and `results` capability.
10. Performance or reliability data never grants authorization.
11. Historical context snapshots are evidence/presentation only and never grant permission.
12. Numeric Event metrics are aggregated only within compatible Event Type scope + metric definition semantics.

## 5. Workflows

### Record an occurrence result

An exact-target Event manager records or corrects the occurrence outcome, score, opponent score, rank, notes, and supported metrics. Saving again updates the same occurrence result rather than creating another row.

### Record a Player result

A manager chooses an eligible Player and records that Player's outcome, score, rank, notes, and supported metrics. Saving the same occurrence/Player pair again updates that Player's existing result.

### Player history view

The active Player may view that exact Player's historical Event results across Player-, Alliance-, and Kingdom-scoped Events. Switching active Player switches the personal history; sibling Players never merge.

### Alliance historical intelligence

Current authorized Alliance leadership may view all historical Events permanently targeted at that Alliance, including results for Players who later left or changed Alliance. Historical queries begin from immutable `event.alliance_id`, not current memberships.

### Kingdom historical intelligence

Current authorized Kingdom leadership may view all historical Events permanently targeted at that Kingdom, including results for Players who later transferred Kingdoms. Historical queries begin from immutable `event.kingdom_id`, not current `players.current_kingdom_id`.

### Manager planning view

For current/future operations, the Event Manage workspace may show current eligible Players plus compatible historical intelligence. Planning eligibility remains current-state logic; historical record ownership remains immutable Event-target logic.

## 6. Authorization and privacy

Result mutation is manager-only and follows the Event's exact Player, Alliance, or Kingdom target permission. A route Player ID identifies the result subject, never the actor persona.

Self-specific result/intelligence cards are returned only for the authenticated User's currently active Player. Organization-wide historical views require current authority for the exact Alliance/Kingdom target.

Former leadership loses organization-wide access when current authority is lost. New leadership inherits organization-owned history from before its tenure. Platform Administrator status grants no game-domain Event-history bypass.

## 7. Persistence and query semantics

Events owns occurrence results, Player results, normalized Event metric definitions/values, and occurrence-time historical Player context.

Player lifetime history queries use durable `player_id` across all Event targets. Alliance history queries use immutable Event `alliance_id`. Kingdom history queries use immutable Event `kingdom_id`.

Current-state planning intelligence may intentionally restrict to the current exact scope/target and compatible Event Type scope. Historical contribution reporting does not discard old records because a Player's current affiliation changed.

Comparable score summaries include result count, average score, best score, and latest score only when score semantics are compatible. Other metrics follow their own definition's aggregation rules.

## 8. Reliability semantics

Reliability is deliberately narrow and evidence-based.

- **completed** — attendance is present, or a roster/Rally assignment is recorded as participated;
- **absent** — attendance, roster, or Rally outcome is recorded absent and is not excused;
- **excused** — attendance is explicitly excused;
- **commitments** — explicit registration or confirmed/participated/absent roster or Rally commitments;
- **unresolved** — a commitment exists without a completed, absent, or excused outcome.

Reliability percentage is:

```text
completed / (completed + absent) × 100
```

Excused and unresolved cases are excluded from that denominator. Mere eligibility, roster visibility, User ownership, or current Alliance membership does not count as an absence.

## 9. Events, integrations and background processing

Result mutations emit audit evidence and scope-aware outbox messages partitioned by the parent Event target: `player:{id}`, `alliance:{id}`, or `kingdom:{id}`.

No background worker changes historical ownership. Contributions may compose Event facts into reports/history but does not duplicate those facts into a second canonical ledger merely for reporting.

## 10. Failure, idempotency and concurrency

- unsupported Event Type capabilities fail closed;
- ineligible Player result targets fail closed;
- Event scope/target mutation after creation fails closed;
- occurrence and Player result writes serialize on the occurrence/result boundary;
- database uniqueness prevents duplicate occurrence or occurrence/Player result rows;
- retained historical result references restrict destructive Player/Event target deletion where required; and
- updating a result is idempotent with respect to its natural occurrence or occurrence/Player key.

## 11. Tests and validation

Tests protect multi-Player User isolation, result upsert behavior, Player eligibility, exact target scoping, historical ownership across Alliance/Kingdom movement, conservative reliability semantics, compatible metric aggregation, separate recorder identity, and absence of membership identity in result persistence.

## 12. Related documentation

- [Events domain](README.md)
- [Event contribution and historical intelligence](event-contribution-history.md)
- [Event registration and attendance](registration-and-attendance.md)
- [Event rosters](rosters.md)
- [Event battle plans](battle-plans.md)
- [Rallies](../rallies/README.md)
- [Contributions](../contributions/README.md)
- [Authorization](../authorization/README.md)
- [Kingdoms / Player identity](../kingdoms/README.md)
