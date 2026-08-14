# Event reminders

[← Notifications domain](README.md)

**Document type:** Living capability contract  
**Status:** Current  
**Owning domain:** Notifications

## 1. Purpose

Defines Event reminder rules, Player-specific audience resolution, due-time queueing, deterministic delivery identity, and completion through the shared outbox.

## 2. Scope and non-scope

In scope:

- Event reminder rule creation, disabling, and re-enabling;
- Event-start and poll-close reminder triggers;
- audiences based on target, response, registration, roster selection, or all eligible Players in the Event scope;
- Player-specific reminder deliveries;
- due-time materialization and queueing;
- in-app delivery handoff through Platform outbox; and
- idempotent scheduler recovery.

Generic email/SMS/push and webhook transport are outside this capability.

## 3. Identity and model

A delivery is unique by `rule_id + occurrence_id + player_id` and also stores the Player's current `recipient_user_id`.

This deliberately does not collapse multiple Players owned by one User: Player A and Player B receive independent delivery rows even when both resolve to the same User account.

`due_at` is derived from the configured trigger: occurrence start minus `minutes_before` for `before_start`, or the referenced poll close time minus `minutes_before` for `before_poll_close`.

Delivery states are `pending`, `queued`, `sent`, `failed`, and `skipped`.

## 4. Invariants

1. Reminder identity is Player-specific.
2. Audience resolution uses current Event participation and current Player eligibility.
3. Only Players with an authoritative `players.user_id` receive in-app deliveries.
4. A User owning multiple eligible Players receives one delivery for each Player.
5. Repeated scheduler execution cannot create duplicate deliveries or outbox requests.
6. `target` audience is valid only for Player-scoped Events.
7. `responded` includes Going/Maybe, not Unavailable.
8. `registered` includes registered seats, not waitlisted/cancelled rows.
9. `rostered` includes current Assigned/Confirmed roster assignments for the exact occurrence and excludes Declined/Removed assignments.
10. `all_scope_players` resolves exact Player/Alliance/Kingdom eligibility at queue time.
11. Duplicate active rule definitions are no-ops; a matching disabled definition is re-enabled rather than duplicated.
12. `before_poll_close` requires an exact poll from the same Event with a close timestamp.
13. A poll has at most one intended active deadline rule: changing the lead time disables the previous definition, and closing/removing the deadline disables active poll-close rules.
14. A poll-close rule is queued only while the referenced poll is open and before it closes.
15. `sent` means the durable in-app outbox handoff was published; it does not claim third-party transport delivery.

## 5. Workflows

### Configure rule

An exact-target Event manager creates an Event-start or poll-close rule. Poll configuration synchronizes its deadline rule so changing the lead time cannot leave duplicate active reminders. Audience choices are constrained by Event capabilities. Reminder rule authorship records authenticated User and active Player persona separately. Submitting an already-active definition returns the existing rule without duplicate audit/outbox evidence; submitting a matching disabled definition re-enables it.

### Disable rule

An exact-target Event manager can disable an enabled rule. Disabled rules remain persisted for operational history but are ignored by due-time queueing.

### Resolve and queue

`events:queue-reminders` scans enabled rules and resolves each due timestamp from either occurrence start or the referenced open poll close time. It resolves the audience from current Player/Event facts, creates missing Player-specific deliveries, and creates a deterministic `event.reminder.requested` outbox message.

### Mark sent

When Platform publishes the matching outbox event, Notifications advances the delivery to `sent` and records `sent_at`.

### Catch up

Scheduler/outbox execution can be rerun safely because delivery and outbox identities are deterministic.

## 6. Authorization and privacy

Rule configuration uses the Event's exact manage permission. The active Player persona never grants manager authority.

Delivery recipient identity comes from `players.user_id` at queue time. A Player without an owned User account is not an in-app recipient.

## 7. Persistence and query semantics

Notifications owns `event_reminder_rules` and `event_reminder_deliveries`. Poll-close rules reference Events-owned `event_polls` through `poll_id`; the trigger/reference pairing is enforced by the database. Events owns occurrences, responses, registrations, roster assignments, and Player eligibility facts used by audience resolution. Platform owns the outbox.

## 8. Events, integrations and background processing

The scheduler runs `events:queue-reminders` every minute with overlap prevention. Event reminder outbox partitioning follows the Event target: `player:{id}`, `alliance:{id}`, or `kingdom:{id}`.

## 9. Failure, idempotency and concurrency

- delivery identity uses a deterministic SHA-256 key;
- duplicate scheduler runs reuse the unique rule/occurrence/Player tuple;
- stale Player eligibility is filtered during audience resolution;
- closed/cancelled/expired polls do not queue poll-close deliveries;
- Player ownership changes are reflected in recipient resolution for new deliveries;
- outbox publication is independently retryable.

## 10. Operations and observability

Inspect rule, occurrence, subject Player, recipient User, due time, delivery status, matching outbox row, partition key, and completion timestamp separately.

## 11. Tests and validation

Tests protect rule lifecycle/idempotency, Event-start and poll-close due-time semantics, rostered/response/registration audience semantics, separate deliveries and inbox views for multiple Players owned by one User, scheduler idempotency, exact Event target authorization, and outbox completion state.

## 12. Related documentation

- [Notifications domain](README.md)
- [Events](../events/README.md)
- [Event participation](../events/registration-and-attendance.md)
- [Event phases and polls](../events/polls-and-phases.md)
- [Event rosters](../events/rosters.md)
- [Platform transactional outbox](../platform/transactional-outbox.md)
