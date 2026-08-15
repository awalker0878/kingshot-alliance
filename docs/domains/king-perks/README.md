# King Perks domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** KING-PERKS-001 functional implementation complete; verification and hardening in progress  
**Code owner:** `app/Domain/KingPerks`  
**Primary authorization boundary:** current active Player + exact Kingdom Event authority

## Purpose

King Perks coordinates KingShot Kingdom appointment rotations and Kingdom-wide King Skill timing around a Kingdom of Power preparation window. It is an operational planning domain: it does not claim to appoint a Player or activate a King Skill inside KingShot itself.

## Ownership and integration

King Perks owns:

- the Kingdom of Power King Perks plan;
- Player appointment requests and declared availability;
- duration-aware appointment rotation state;
- explicit position-cooldown blocks;
- live appointment evidence;
- King Skill planning state; and
- King Perks reminder delivery state.

Events remains the owner of Kingdom of Power Event identity, occurrence, target and Preparation timing. Authorization remains the owner of `events.kingdom.*` grants. Kingdoms remains the owner of durable Kingdom and Player identity. Notifications owns the durable reminder handoff semantics and Platform owns the transactional outbox.

`EventCapability::KingPerks` is installed as a Kingdom-scoped extension capability for `kingdom-of-power` by `KingPerkEventCapabilityCatalog`. Leadership mutations reuse the Event scope's existing `events.kingdom.manage` permission through `EventMutationAuthority`; no parallel `kingdom.perks.*` permission family is introduced.

## Temporal rules

Appointment time is catalogue-driven rather than controlled by a global scheduler slot size. The current catalogue records separately:

- appointment occupancy: 30 minutes;
- Player cooldown: 60 minutes;
- Player cooldown anchor: `appointment_end`; and
- position cooldown after a live appointment cancellation: 30 minutes.

The cooldown anchor is intentionally explicit. The public rule confirms a Player cooldown but is not sufficiently precise about its timer anchor for this application to bury an assumption in scheduling code. Until the mechanic is verified in-game, the scheduler uses the conservative appointment-end anchor. A verified change belongs in the appointment catalogue.

Appointment `ends_at` is always derived from `starts_at + appointment duration`; clients cannot choose an arbitrary end time. Player availability must contain the entire derived appointment interval.

King Skill effect duration is persisted explicitly at planning time from the duration verified in game. The application does not invent a skill duration. Advance scheduling lead time is a separate King Skill rule and is never used as effect duration.

## Requests and smart scheduling

Players submit requests against the published plan using an activity category:

- construction;
- research;
- training;
- healing; or
- combat.

A request stores Player availability, optional preferred applicable appointment, optional planned speedup minutes, optional planned resource amount and notes.

There is deliberately no universal Player score. Auto-scheduling operates within one activity category at a time and ranks declared usage only among candidates for that category. The current training strategy tries Noble Advisor first and then uses Chief Minister as overflow. Leadership may always override the result manually.

Auto-scheduling may assign a Player only when:

1. the Player is still in the plan Kingdom;
2. the request is still submitted;
3. the full appointment interval is inside the request's availability;
4. the requested/preferred appointment is compatible with the category;
5. the position is not occupied or blocked; and
6. the Player is not appointed or inside a post-appointment cooldown.

## Preparation strategy presets

The management workspace exposes recommended Preparation-day combinations separately from hard rules. Presets can prefill the planner but never create assignments or King Skill activations by themselves.

This distinction is intentional: event timing and temporal legality are application invariants; recommended activity/skill/appointment combinations are strategy guidance and remain manually overridable.

## Live operations

The leadership workspace presents Now / Next / Following lanes for every supported appointment position.

Live evidence records planned time separately from:

- `actual_started_at`;
- `actual_ended_at`;
- completion; and
- no-show state.

A rapid replacement preserves history by recording the original assignment as a no-show and creating a new appointment for the replacement Player. It does not overwrite the historical assigned Player.

If a live appointment is cancelled in game, leadership records the cancellation time and the application creates an explicit position block for the catalogue-defined cancellation cooldown. A replacement cannot be moved into that blocked interval.

## Core invariants

1. A plan belongs to exactly one Kingdom of Power occurrence and its immutable Kingdom target.
2. The plan snapshots the occurrence's explicit Preparation phase when present; otherwise it derives the window from the Event's persisted `preparation_phase_minutes` setting.
3. An appointment must fit entirely inside the plan window.
4. One appointment position cannot overlap itself.
5. The same Player cannot directly occupy overlapping positions.
6. A position cooldown block cannot overlap a new appointment for the same position.
7. A Player cannot receive another appointment until the earlier appointment and catalogue-defined post-appointment cooldown have elapsed.
8. The assigned Player must currently belong to the exact target Kingdom at assignment time.
9. Appointment confirmation and decline are Player self-service: the active Player must exactly equal `assigned_player_id` and still pass exact-Kingdom Event view authority.
10. Player requests are visible to leadership but self-mutation remains exact active-Player authority.
11. Leadership plan/request/appointment/skill mutations require current `events.kingdom.manage` authority through the existing Event mutation boundary.
12. Platform Administrator status provides no game-domain bypass.
13. King Skill planning records both activation and computed end time from an explicit effect duration.
14. All canonical timestamps are stored in UTC; the UI additionally renders browser-local time.
15. Historical completed/no-show/cancelled evidence is never rewritten to follow later Kingdom transfers.

## Concurrency and database enforcement

All leadership mutations acquire Event authority before locking the King Perks plan and then the subordinate request/appointment/skill row. This follows the existing Event mutation lock order.

PostgreSQL uses exclusion constraints for direct appointment-position and Player time overlap. SQLite test environments use equivalent triggers. Service-level checks additionally enforce post-appointment Player cooldowns and position block intervals.

The plan lock serializes competing King Perks mutations for one occurrence, while database guards remain the final defense against impossible direct overlaps.

## Reminders

King Perks reuses Notifications + Platform outbox rather than introducing a separate transport.

Player appointment reminders are queued at the configured 24-hour, 1-hour and 10-minute leads. If an appointment is still unconfirmed at 10 minutes, current Players holding `events.kingdom.manage` for the exact Kingdom are notified.

King Skill leadership reminders are generated when the configured advance scheduling window opens and one hour before activation. Manager recipients are resolved from current exact-Kingdom authority at queue time, not frozen when the plan is created.

Deliveries have deterministic idempotency keys. Publishing `king_perks.reminder.requested` marks the durable in-app handoff sent; it does not claim third-party transport delivery.

## Audit and outbox evidence

Events include:

- `king_perks.plan_created`
- `king_perks.plan_published`
- `king_perks.request_submitted`
- `king_perks.request_withdrawn`
- `king_perks.request_declined`
- `king_perks.request_scheduled`
- `king_perks.appointment_assigned`
- `king_perks.appointment_reassigned`
- `king_perks.appointment_confirmed`
- `king_perks.appointment_declined`
- `king_perks.appointment_activated`
- `king_perks.appointment_completed`
- `king_perks.appointment_no_show`
- `king_perks.appointment_cancelled`
- `king_perks.skill_planned`
- `king_perks.skill_scheduled`
- `king_perks.skill_activated`
- `king_perks.reminder.requested`

All interactive actor attribution uses Player identity. Kingdom outbox partitioning uses the exact Event Kingdom. System reminder materialization identifies itself with `origin=system` in the outbox payload.

## UX surfaces

`/events/{event}/king-perks` is the leadership operations workspace. It contains:

- preparation strategy cards;
- Now / Next / Following live lanes;
- Player request queue;
- category-specific smart fill;
- duration-aware appointment rotation;
- live replacement and outcome recording;
- explicit position cooldowns; and
- King Skill planning/state.

`/events/{event}/king-perks/my` is Player self-service. It exposes only the active Player's assignments and requests for the published/active plan.

## Verification gate

KING-PERKS-001 is not considered complete until the branch is green for the repository's PostgreSQL/schema suite, Pint, Larastan, targeted backend contracts, frontend lint/format/type checks and targeted production build. CI results, rather than implementation presence alone, determine the final phase status.

Verification workflows are read-only: they may report formatting or static-analysis failures, but they must never rewrite or push application source from a pull-request run.
