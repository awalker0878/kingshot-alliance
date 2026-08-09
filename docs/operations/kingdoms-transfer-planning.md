# Kingdoms transfer planning operations

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice B / participant direction and destination candidate

## Runtime shape

Transfer planning remains synchronous request/response behavior using PostgreSQL plus the existing audit and transactional-outbox infrastructure. Slice B adds no Kingdoms-specific scheduler, queue, crawler, bot, or external game integration.

## Migrations

Apply in dependency order:

1. `2026_08_09_090000_create_transfer_plans.php`
2. `2026_08_09_100000_create_transfer_participants.php`

Rollback must reverse that order:

1. drop `transfer_participants`;
2. drop `transfer_plans`;
3. only then roll back older Kingdoms tables if required.

The participant table owns tenant workflow state and references accepted roster/player/membership/Kingdom records. No accepted `KINGDOMS-001` table is repurposed as transfer state.

## Operational diagnosis

For participant mutation failures, check:

1. active Alliance context is correct;
2. actor has `kingdoms.manage`;
3. recent password confirmation is present;
4. plan belongs to the active Alliance;
5. plan is `draft` or `open`;
6. `alliances.kingdom_id` still matches `transfer_plans.home_kingdom_id`;
7. roster-bound players belong to this Alliance and are active/tracked;
8. optional membership links belong to this Alliance and are active; and
9. source/destination Kingdom values satisfy direction rules.

An outgoing participant with no destination is valid and should appear as undecided, not as a data failure.

An incoming participant with no roster, membership, source Kingdom, or stable game ID is also valid. Do not create a neutral game identity from display name alone.

## Identity diagnosis

If an incoming stable ID is supplied with a source Kingdom, the neutral identity is resolved under that source Kingdom. A stable ID without a source Kingdom remains plan-scoped.

If an update attempts to change a known roster identity, known source, known stable game ID, or resolved neutral player, the workflow fails closed and requires withdraw + recreate.

Destination changes never move a `KingdomPlayer` between Kingdom records.

## Audit/outbox evidence

Material changes should produce matching `audit_events` and `outbox_messages` entries using:

- `kingdoms.transfer_participant_created`;
- `kingdoms.transfer_participant_updated`;
- `kingdoms.transfer_participant_withdrawn`; and
- the existing `kingdoms.transfer_plan_*` lifecycle events.

Private manager notes must not appear in event metadata.

`kingdoms.*` remains excluded from external webhook delivery.

## Recovery

Withdrawal is the reversible workflow exit for an incorrect participant row. It preserves history and permits a corrected replacement row.

For home-Kingdom drift, do not rewrite the plan's captured home context. Cancel the stale plan and create a deliberate replacement under the Alliance's current Kingdom.

No Slice B operation automatically changes roster state or performs transfer completion; roster handoff remains deferred to Slice D.
