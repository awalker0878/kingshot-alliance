# Kingdoms transfer group security review

**Increment:** `KINGDOMS-002`  
**Slice:** C1 / `K2-P3` transfer groups and coordinators  
**Status:** Candidate security review

## Security boundary

Transfer groups are alliance-owned workflow data. `Kingdom` and `Player` remain neutral global references and never become authorization boundaries.

Every group mutation re-resolves the active Alliance and transfer plan. Existing group and participant identifiers are then constrained by both `alliance_id` and `transfer_plan_id` before mutation.

Submitted coordinator membership IDs are resolved only among active memberships of the active Alliance. Submitted group IDs used for participant assignment must identify an active group in the same Alliance and transfer plan.

## Authorization and privilege confusion

Group create/update/archive and participant group assignment require:

- authenticated active Alliance context;
- `kingdoms.manage`; and
- recent password confirmation at the route boundary.

Coordinator assignment is workflow responsibility only. It does not modify roles, attach permissions, create policy exceptions, or grant `kingdoms.manage`.

A user who is only an ordinary Member remains unable to mutate transfer groups even if that membership is recorded as a group's coordinator. This regression is covered by feature tests.

## Destination manipulation

Groups accept only moving directions (`incoming`, `outgoing`). Staying participants cannot be assigned to a moving group.

Incoming group destination is normalized to the transfer plan's captured home Kingdom. A submitted alternate destination is ignored rather than stored.

Outgoing group destination may be undecided. If present it must be a valid active canonical Kingdom and cannot equal the plan home Kingdom.

Destination compatibility is checked in all mutation directions:

- assigning a participant to a group;
- changing group direction/destination while participants are assigned; and
- changing a grouped participant's direction/destination.

Incompatible changes fail in the same database transaction. C1 never silently rewrites participant direction or destination to satisfy a group edit.

## Tenant and object-ID tampering

The following submitted identifiers are tenant-scoped before use:

- transfer plan ID;
- transfer group ID;
- transfer participant ID; and
- coordinator membership ID.

A group from another Alliance cannot be edited through the current plan and cannot be assigned to a participant in the current Alliance, even if both Alliances share the same Kingdom or neutral player references.

## Privacy

Group manager notes are management-only tenant data.

Ordinary member payloads may include only operationally safe group information:

- group name;
- incoming/outgoing direction;
- safe destination display; and
- coordinator display name.

Member payloads exclude:

- group ID;
- coordinator membership ID;
- participant transfer-group ID;
- group manager notes;
- participant manager notes; and
- private membership details.

Audit/outbox metadata contains group/participant identifiers and workflow direction/destination/coordinator identifiers as needed for attributable durability, but does not copy private manager note text.

## Lifecycle and stale-plan safety

Group/assignment changes are allowed only while a plan is Draft/Open. Locked/Closed/Cancelled plans are read-only.

If the Alliance current Kingdom differs from the plan's captured home Kingdom, group and assignment mutations fail closed. The plan is not silently reconciled to a new Kingdom.

A group cannot be archived while active participants remain assigned. Archive is idempotent after the group is eligible, preserving workflow history without duplicate audit/outbox evidence.

## External integration boundary

New events remain within the existing internal `kingdoms.*` event family:

- `kingdoms.transfer_group_created`;
- `kingdoms.transfer_group_updated`;
- `kingdoms.transfer_group_archived`; and
- `kingdoms.transfer_participant_group_changed`.

The existing generic webhook boundary excludes `kingdoms.*`, so Slice C1 creates no new public webhook/API contract.

## Deferred risks

This review does not approve readiness/blocker visibility, transfer eligibility/resources, completion/handoff, marketplace/public advertising, diplomacy intelligence, cross-alliance rankings, automated recommendations, or automated game ingestion. Those remain outside Slice C1.
