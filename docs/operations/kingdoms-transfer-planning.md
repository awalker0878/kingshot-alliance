# Kingdoms transfer planning operations

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice C1 / transfer groups and coordinators candidate

## Runtime shape

Transfer planning remains synchronous request/response behavior using PostgreSQL plus the existing audit and transactional-outbox infrastructure. Slice C1 adds no Kingdoms-specific scheduler, queue, crawler, bot, or external game integration.

## Migrations

Apply in dependency order:

1. `2026_08_09_090000_create_transfer_plans.php`
2. `2026_08_09_100000_create_transfer_participants.php`
3. `2026_08_09_110000_create_transfer_groups.php`

The C1 migration creates `transfer_groups` and adds nullable `transfer_group_id` to `transfer_participants`.

Rollback must reverse that order:

1. drop the participant group foreign key/column and `transfer_groups` through the C1 migration;
2. drop `transfer_participants`;
3. drop `transfer_plans`; and
4. only then roll back older Kingdoms tables if required.

No accepted `KINGDOMS-001` table is repurposed as transfer state.

## Operational diagnosis

For group or participant-group mutation failures, check:

1. active Alliance context is correct;
2. actor has `kingdoms.manage`;
3. recent password confirmation is present;
4. plan belongs to the active Alliance;
5. plan is `draft` or `open`;
6. `alliances.kingdom_id` still matches `transfer_plans.home_kingdom_id`;
7. group belongs to this plan/Alliance and is `active`;
8. optional coordinator membership is active and belongs to this Alliance;
9. group direction is `incoming` or `outgoing`;
10. incoming group destination equals the captured plan home Kingdom;
11. outgoing group destination is either undecided or another active Kingdom;
12. staying participants are not assigned to moving groups; and
13. assigned participant direction/destination remains compatible with the group.

Compatibility failures are expected fail-closed behavior. Operators should move/unassign the incompatible participant or deliberately update participant intent before changing a destination-bound group; the application does not rewrite participant intent automatically.

## Identity and destination diagnosis

Slice B identity rules remain unchanged. Incoming stable identity is resolved only with source Kingdom + stable game-player ID; display name alone never merges neutral identity.

Group destination changes never move a `KingdomPlayer` between Kingdom records. Group data is coordination state, not neutral game identity.

## Coordinator diagnosis

Coordinator assignment is not authorization. If a named coordinator cannot mutate groups/participants, verify their normal Alliance permissions rather than adding a special coordinator bypass. C1 intentionally provides no coordinator-derived permission.

If a coordinator can no longer be selected, verify that the associated Alliance membership is still active and belongs to the current Alliance.

## Archive and recovery

A group with active participants cannot be archived. Move or unassign those active participants first.

Archive retries are idempotent. Archived groups remain historical workflow records and are excluded from active member group lists. Active group names are case-insensitively unique within a plan; an archived name can later be reused.

For home-Kingdom drift, do not rewrite the plan's captured home context. Cancel the stale plan and create a deliberate replacement under the Alliance's current Kingdom.

## Audit/outbox evidence

Material C1 changes should produce matching `audit_events` and `outbox_messages` entries using:

- `kingdoms.transfer_group_created`;
- `kingdoms.transfer_group_updated`;
- `kingdoms.transfer_group_archived`;
- `kingdoms.transfer_participant_group_changed`;
- existing `kingdoms.transfer_participant_*`; and
- existing `kingdoms.transfer_plan_*` lifecycle events.

Private participant/group manager notes must not appear in event metadata/payloads.

`kingdoms.*` remains excluded from external webhook delivery.

## Deferred operations

Slice C1 performs no readiness/blocker workflow and no roster completion/handoff. Those remain explicit later slices and must not be inferred from group/coordinator state.
