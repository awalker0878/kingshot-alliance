# Kingdoms transfer planning operations

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice C2 / readiness and blockers candidate

## Runtime shape

Transfer planning remains synchronous request/response behavior using PostgreSQL plus the existing audit and transactional-outbox infrastructure. Slice C2 adds no Kingdoms-specific scheduler, queue, crawler, bot, external game integration, eligibility engine, or automated readiness worker.

## Migrations

Apply in dependency order:

1. `2026_08_09_090000_create_transfer_plans.php`
2. `2026_08_09_100000_create_transfer_participants.php`
3. `2026_08_09_110000_create_transfer_groups.php`
4. `2026_08_09_120000_create_transfer_readiness_and_blockers.php`

The C2 migration adds current `readiness_state` to `transfer_participants`, creates append-only `transfer_readiness_transitions`, and creates private `transfer_blockers` with creator/resolver provenance. Existing withdrawn participants are normalized to readiness `withdrawn`; no historical actor is fabricated for pre-C2 withdrawals.

Rollback must reverse that order:

1. drop `transfer_blockers` and `transfer_readiness_transitions`, then remove participant `readiness_state` through the C2 migration;
2. drop the participant group foreign key/column and `transfer_groups` through C1;
3. drop `transfer_participants`;
4. drop `transfer_plans`; and
5. only then roll back older Kingdoms tables if required.

No accepted `KINGDOMS-001` table is repurposed as transfer state.

## Readiness diagnosis

For readiness-transition failures, check:

1. active Alliance context is correct;
2. actor has `kingdoms.manage`;
3. recent password confirmation is present;
4. plan and participant belong to the active Alliance/plan boundary;
5. plan is `draft` or `open`;
6. `alliances.kingdom_id` still matches `transfer_plans.home_kingdom_id`;
7. participant has not already been withdrawn;
8. requested transition is one of the explicit allowed workflow transitions;
9. entering `blocked` has at least one active blocker;
10. leaving `blocked` for an active readiness state has no active blockers; and
11. `ready` or `confirmed` has no active blockers.

Invalid jumps are expected fail-closed behavior. Do not update `readiness_state` directly to bypass workflow meaning or append-only transition history.

`confirmed` is planning state only. It must not be interpreted operationally as roster completion, player arrival/departure, or K2-P5 handoff evidence.

## Blocker diagnosis

Blocker records are alliance/plan/participant scoped. Creation/resolution requires the same `kingdoms.manage`, password-confirmation, mutable-plan, and home-Kingdom checks as readiness changes.

Resolving the final active blocker does **not** advance readiness. If the participant is still `blocked`, an authorized manager must explicitly choose the next permitted readiness state.

Blocker `summary` and `details` are management-private. Do not write them into general logs, audit metadata, outbox payloads, support diagnostics, or member-facing payloads. Structured diagnostics should identify plan/participant/blocker IDs and failed invariant only.

## Existing group/participant diagnosis

Slice C1 compatibility rules remain unchanged: coordinator assignment is workflow responsibility only; incoming/outgoing group direction and destination must remain compatible with assigned participants; staying participants are not moving-group members; and group changes never rewrite participant intent automatically.

Slice B identity rules also remain unchanged. Incoming stable identity is resolved only with source Kingdom + stable game-player ID; display name alone never merges neutral identity.

## Withdrawal and history

Withdrawal is terminal readiness state and sets the participant's existing `withdrawn_at`. The normal withdrawal route delegates through the readiness transition action so participant terminal state, append-only readiness history, audit evidence, and the existing `kingdoms.transfer_participant_withdrawn` event remain aligned.

Retries are idempotent. Withdrawn participants remain queryable in management history but are excluded from active member/coordination views.

## Query shape

The manager Readiness board eager-loads blocker actor/resolver and readiness-transition actor relations in bounded relation queries rather than per-participant lookups. Keep board/filter changes within this query shape; do not introduce participant-loop database reads.

The feature suite exercises the board with realistic multi-participant data and a bounded query-count assertion.

## Home-Kingdom recovery

For home-Kingdom drift, do not rewrite the plan's captured home context. Cancel the stale plan and create a deliberate replacement under the Alliance's current Kingdom.

## Audit/outbox evidence

Material C2 changes produce internal audit/outbox events using:

- `kingdoms.transfer_readiness_changed`;
- `kingdoms.transfer_blocker_created`;
- `kingdoms.transfer_blocker_resolved`;
- existing `kingdoms.transfer_participant_withdrawn`; and
- existing plan/participant/group event families.

Readiness event metadata may contain scoped IDs, from/to state, blocker ID/state and active-blocker counts. Private blocker summary/details and manager notes must not appear in audit metadata or outbox payloads.

`kingdoms.*` remains excluded from external webhook delivery.

## Deferred operations

Slice C2 performs no roster completion/handoff, transfer execution, inferred eligibility/readiness, transfer-resource/pass optimization, automated player scoring, or external game-data ingestion. Explicit completion and accepted roster-action handoff remain `K2-P5`.
