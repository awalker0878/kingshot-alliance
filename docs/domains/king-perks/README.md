# King Perks domain

[← Domain documentation](../README.md)

**Document type:** Living domain contract  
**Status:** KING-PERKS-001 initial implementation  
**Code owner:** `app/Domain/KingPerks`  
**Primary authorization boundary:** current active Player + exact Kingdom Event authority

## Purpose

King Perks coordinates KingShot Kingdom appointment rotations and Kingdom-wide King Skill timing around a Kingdom of Power preparation window. It is an operational planning domain: it does not claim to appoint a Player or activate a King Skill inside KingShot itself.

## Ownership and integration

King Perks owns plan, appointment, position-cooldown and King Skill scheduling state. Events remains the owner of Kingdom of Power Event identity, occurrence and preparation timing. Authorization remains the owner of `events.kingdom.*` grants. Kingdoms remains the owner of durable Kingdom and Player identity.

`EventCapability::KingPerks` is enabled only for the Kingdom-scoped `kingdom-of-power` Event. Leadership mutations reuse the Event scope's existing `events.kingdom.manage` permission through `EventMutationAuthority`; no parallel `kingdom.perks.*` permission family is introduced.

## Temporal rules

Appointment time is catalogue-driven rather than controlled by a global scheduler slot size. The current catalogue records:

- 30-minute appointment occupancy;
- 60-minute Player cooldown after each appointment; and
- 30-minute position cooldown when a live appointment is cancelled.

Those values are intentionally separate because they protect different resources: the appointment position and the Player. Appointment `ends_at` is derived from `starts_at + appointment duration`; clients do not choose an arbitrary end time.

The official KingShot Help Center currently documents the one-hour post-appointment cooldown and the 30-minute cancelled-position cooldown. Community appointment planners consistently model appointments as 30-minute rotations. If KingShot changes these mechanics, the catalogue is the single application location to update.

King Skill effect duration is persisted explicitly at planning time from the duration shown/verified in game. The application does not invent a duration when a reliable rule is unavailable. King Skills expose the current 48-hour advance scheduling window separately from their effect duration.

## Core invariants

1. A plan belongs to exactly one Kingdom of Power occurrence and its immutable Kingdom target.
2. The plan snapshots the occurrence's explicit Preparation phase when present; otherwise it derives the window from the Event's persisted `preparation_phase_minutes` setting.
3. An appointment must fit entirely inside the plan window.
4. One appointment position cannot overlap itself.
5. A position cooldown block cannot overlap a new appointment for the same position.
6. A Player cannot receive another appointment until the earlier appointment and post-appointment cooldown have both elapsed.
7. The assigned Player must currently belong to the exact target Kingdom at assignment time.
8. Appointment confirmation is Player self-service: the active Player must exactly equal `assigned_player_id` and still pass exact-Kingdom Event view authority.
9. Leadership plan/appointment/skill mutations require current `events.kingdom.manage` authority through the existing Event mutation boundary.
10. Platform Administrator status provides no game-domain bypass.
11. King Skill planning records both activation and computed end time from an explicit effect duration.
12. All canonical timestamps are stored in UTC.

## Audit and outbox evidence

Initial events include:

- `king_perks.plan_created`
- `king_perks.plan_published`
- `king_perks.appointment_assigned`
- `king_perks.appointment_reassigned`
- `king_perks.appointment_confirmed`
- `king_perks.appointment_completed`
- `king_perks.appointment_no_show`
- `king_perks.appointment_cancelled`
- `king_perks.skill_planned`
- `king_perks.skill_scheduled`
- `king_perks.skill_activated`

All actor attribution uses Player identity. Kingdom outbox partitioning uses the exact Event Kingdom.

## Initial UI

`/events/{event}/king-perks` is a dedicated management surface for the first vertical slice. It displays the preparation window, duration/cooldown rules, appointment rotation, explicit position cooldowns, and King Skill activation/effect windows. The existing large Event workspace can link/embed this surface after the backend contract has settled without coupling King Perks persistence to that component.
