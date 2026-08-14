# Event participation and attendance

[← Events domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Events

## 1. Purpose

Defines Player availability responses, registration/waitlist state, cancellation/promotion, and attendance for a concrete Event occurrence.

Response, registration, roster selection, and attendance are independent facts. No workflow may infer one from another.

## 2. Scope and non-scope

In scope:

- `going` / `maybe` / `unavailable` responses;
- optional role/team/availability notes;
- registration windows;
- registered/waitlisted/cancelled state;
- bounded capacity and ordered waitlist promotion;
- manager-recorded present/absent/excused/unknown attendance; and
- Player-specific participation history.

Out of scope: Event scheduling, roster selection, Rally participation, reminder delivery state, and results.

## 3. Identity and model

Every participation row is keyed by `occurrence_id + player_id`.

`players.user_id` is authoritative Player ownership. Self-service actions use the authenticated User's validated active Player context and never accept a Player identity from the form. The actor User and acting Player persona are recorded separately from the subject Player when an operation is attributable.

A Player is eligible when:

- Player scope: the Player is the exact Event target;
- Alliance scope: the Player has an active roster entry in the target Alliance and the Player's current Kingdom matches the Alliance Kingdom; or
- Kingdom scope: the Player's current Kingdom is the exact Event Kingdom.

## 4. Invariants

1. Response, registration, attendance, roster selection, and results remain separate facts.
2. One response, one registration row, and one attendance row may exist per occurrence/Player.
3. Self-service requires authoritative ownership plus exact active Player context.
4. Owning a Player does not bypass Event eligibility.
5. Capacity mutation is serialized on the occurrence.
6. A full Event waitlists only when the Event Type enables the waitlist capability; otherwise it rejects registration.
7. Cancelling a registered seat promotes the first waitlisted Player atomically and re-numbers the remaining waitlist.
8. Attendance does not create or modify registration.
9. Alliance and Kingdom manager authority is evaluated against the exact Event target, never from the active Player persona.

## 5. Workflows

### Respond

An eligible active Player records `going`, `maybe`, or `unavailable`. Repeating the action updates the same response row.

### Register

Registration must be inside the Event's snapshotted registration window. Capacity is checked transactionally. A seat becomes `registered`; if full and waitlisting is supported it becomes `waitlisted` with a deterministic position.

### Cancel

Cancellation marks the Player's registration `cancelled`. When a registered seat is released, the first waitlisted Player is promoted in the same transaction.

### Record attendance

An Event manager records attendance for an eligible Player. The action records the authenticated User and validated acting Player persona when one exists.

## 6. Authorization and privacy

Self-service routes never carry a subject Player ID. The server resolves the active Player context from the authenticated session and revalidates `players.user_id` on every request.

Manager attendance routes accept a subject Player ID because a coordinator is operating on another Player, but the server independently verifies Event management permission and Player eligibility.

Participation is private operational data.

## 7. Persistence and query semantics

Events owns `event_responses`, `event_registrations`, and `event_attendance`.

Registration windows are derived from each Event's snapshotted open/close offsets and the occurrence start. Historical participation remains attached to durable `player_id` when Alliance or Kingdom context changes later.

## 8. Events, integrations and background processing

Participation mutations produce audit and scope-partitioned outbox evidence. Notifications consumes current participation facts when resolving reminder audiences. Other domains must not mutate participation state indirectly.

## 9. Failure, idempotency and concurrency

- response is an upsert by occurrence/Player;
- repeated active registration returns the existing registration;
- registration outside the window fails;
- bounded capacity is checked while the occurrence row is locked;
- cancellation/promotion occurs under the same lock;
- cross-target or stale roster eligibility fails closed;
- active Player mismatch fails closed.

## 10. Operations and observability

Diagnose occurrence, subject Player, response, registration status, waitlist position, attendance, registration window, target scope, actor User, and actor Player persona independently.

## 11. Tests and validation

Tests protect response upsert, registration windows, capacity/waitlist promotion, independence of participation facts, exact target eligibility, active-Player self-service, manager attendance authorization, and multi-Player User behavior.

## 12. Related documentation

- [Events domain](README.md)
- [Notifications](../notifications/README.md)
- [Event reminders](../notifications/event-reminders.md)
- [Rallies](../rallies/README.md)
