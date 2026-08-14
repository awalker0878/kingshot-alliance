# Event results and Player intelligence

[← Events domain](README.md)

**Document type:** Living capability contract
**Status:** Current
**Owning domain:** Events

## 1. Purpose

Defines occurrence-level results, durable Player results, and planning intelligence for Event Type scopes that enable the `results` capability.

## 2. Scope and non-scope

In scope:

- one summary result for an Event occurrence;
- one result row per `(occurrence_id, player_id)`;
- score, rank, outcome, notes, and structured metrics;
- manager result entry and correction;
- active-Player result display;
- same-target historical Player intelligence; and
- reliability derived from explicit participation outcomes.

Out of scope: automated game telemetry ingestion, subjective Player ratings, authorization derived from performance, and aggregation by User account.

## 3. Identity and model

`EventResult` is occurrence-level operational history. `EventPlayerResult` is Player-level operational history and is keyed by durable `player_id`.

`players.user_id` is authoritative Player ownership. A single User may own any number of Player rows. Those Players keep independent Event result and intelligence histories even when they share the same `user_id`.

Result subject and result recorder are separate concepts:

- `player_id` identifies the Player whose result is being recorded;
- `recorded_by_user_id` identifies the authenticated account that performed the mutation; and
- `recorded_by_player_id` identifies that User's active Player persona when one is selected.

## 4. Invariants

1. At most one `EventResult` exists per occurrence.
2. At most one `EventPlayerResult` exists per occurrence and Player.
3. Player results may be recorded only for a Player eligible for the exact Event target.
4. Result persistence never uses Alliance membership as Player identity.
5. Player result history survives Alliance movement because it is keyed by durable Player identity.
6. Multiple Players owned by one User never share or merge result rows.
7. The active Player Context affects actor persona and self display only; it does not grant Event management permission.
8. Event result writes require the exact Event manage permission and `results` capability.
9. Historical intelligence is restricted to occurrences of Events with the same exact scope and target.
10. Performance or reliability data never grants authorization.

## 5. Workflows

### Record an occurrence result

An exact-target Event manager records or corrects the occurrence outcome, score, opponent score, rank, notes, and optional structured metrics. Saving again updates the same occurrence result rather than creating another row.

### Record a Player result

A manager chooses an eligible Player and records that Player's outcome, score, rank, notes, and optional metrics. Saving the same occurrence/Player pair again updates that Player's existing result.

### Player view

The Event Show workspace presents the active eligible Player's result and historical planning intelligence. Switching the global Player Context switches the Player-specific view without changing ownership or Event authorization.

### Manager intelligence view

The Event Manage workspace shows eligible Players independently, including commitment/outcome counts and score history useful for roster and battle planning.

## 6. Authorization and privacy

Result mutation is manager-only and follows the Event's exact Player, Alliance, or Kingdom target permission. A route Player ID identifies the result subject, never the actor persona.

Self-specific result/intelligence cards are returned only for the authenticated User's currently active Player when that Player is eligible for the Event. Player Context is revalidated from `players.user_id` on every request.

## 7. Persistence and query semantics

Events owns `event_results` and `event_player_results`.

Reliability intelligence is computed from historical occurrences whose parent Events have the same exact scope and target as the current Event. Score summaries use the narrower set of historical occurrences with the same exact target **and Event Type scope**, so unlike scoring systems are never averaged together. Queries batch registrations, roster commitments, Rally commitments, attendance, and Player scores for all eligible Players rather than issuing per-Player query loops.

Comparable score summaries include result count, average score, best score, and latest score when present.

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

Excused and unresolved cases are excluded from that denominator. Mere eligibility, roster visibility, User ownership, or Alliance membership does not count as an absence.

## 9. Events, integrations and background processing

Result mutations emit audit evidence and scope-aware outbox messages partitioned by the parent Event target: `player:{id}`, `alliance:{id}`, or `kingdom:{id}`.

No background worker changes scores or reliability. Intelligence is derived from stored operational facts at query time.

## 10. Failure, idempotency and concurrency

- unsupported Event Type capabilities fail closed;
- ineligible Player result targets fail closed;
- occurrence and Player result writes serialize on the occurrence boundary;
- database uniqueness prevents duplicate occurrence or occurrence/Player result rows;
- Player deletion is restricted while Player-result history exists; and
- updating a result is idempotent with respect to its natural occurrence or occurrence/Player key.

## 11. Tests and validation

Tests protect multi-Player User isolation, result upsert behavior, Player eligibility, exact target scoping, conservative reliability semantics, score aggregation, database uniqueness, separate recorder identity, and absence of membership identity in result persistence.

## 12. Related documentation

- [Events domain](README.md)
- [Event registration and attendance](registration-and-attendance.md)
- [Event rosters](rosters.md)
- [Event battle plans](battle-plans.md)
- [Rallies](../rallies/README.md)
- [Authorization](../authorization/README.md)
- [Kingdoms / Player identity](../kingdoms/README.md)
