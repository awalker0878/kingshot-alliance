# Kingdoms transfer planning operations

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice D / explicit completion and roster handoff candidate

## Runtime shape

Transfer planning remains synchronous request/response behavior using PostgreSQL plus the existing audit and transactional-outbox infrastructure. Slice D adds no Kingdoms-specific scheduler, queue, crawler, bot, external game integration, eligibility engine, automated readiness worker, bulk completion worker, or in-game transfer executor.

Completion delegates roster mutations to the accepted `KINGDOMS-001` actions inside the transfer completion transaction.

## Migrations

Apply in dependency order:

1. `2026_08_09_090000_create_transfer_plans.php`
2. `2026_08_09_100000_create_transfer_participants.php`
3. `2026_08_09_110000_create_transfer_groups.php`
4. `2026_08_09_120000_create_transfer_readiness_and_blockers.php`
5. `2026_08_09_130000_create_transfer_completions.php`

The Slice D migration adds one completion table only. `transfer_participant_id` is unique so one participant cannot acquire duplicate completion/handoff records.

Rollback reverses that order: drop `transfer_completions` before readiness/blockers, groups, participants and plans. No accepted `KINGDOMS-001` table is repurposed as transfer state.

## Lifecycle operations

Planning mutations occur while a cycle is `draft` or `open`.

Use `locked` only when planning is frozen and real-world outcomes are ready to be recorded. Completion is rejected outside `locked`.

A locked plan cannot close while any non-withdrawn participant lacks completion. If a participant will no longer take part, withdraw the participant before locking; withdrawal is intentionally unavailable once the plan is locked.

Do not update transfer or roster tables manually to bypass this lifecycle.

## Completion diagnosis

For a failed completion, check in order:

1. active Alliance context;
2. actor has `kingdoms.manage`;
3. recent password confirmation exists;
4. submitted plan belongs to the active Alliance;
5. plan state is `locked`;
6. `alliances.kingdom_id` still equals captured `transfer_plans.home_kingdom_id`;
7. participant belongs to the same Alliance and plan;
8. participant is not withdrawn;
9. participant readiness is `confirmed`; and
10. direction-specific roster handoff requirements below.

### Incoming

If no existing roster result is supplied, accepted `SaveRosterEntry` creation is used. This does not create a player snapshot.

If an existing roster entry is selected, it must be active/tracked and same-alliance. When the transfer participant carries a stable game-player identifier, it must match the selected roster player's identifier. Existing roster private fields/lifecycle state are preserved; display name alone is never used to select or merge an existing roster identity.

### Outgoing

The participant's captured same-alliance roster entry is re-resolved. Its neutral player binding must still match the transfer participant. Accepted `MarkRosterEntryLeft` performs the lifecycle change. A roster entry already marked left is safe to hand off because that delegated action is idempotent.

### Staying

The same-alliance roster binding must still exist and remain active/tracked. Completion records the transfer outcome only; no roster lifecycle action is called.

## Retry/idempotency diagnosis

Completion locks Alliance → plan → participant and checks the participant's unique completion before delegated roster effects. Retrying a completed participant should return the existing completion and should not produce another roster lifecycle event or another completion audit/outbox event.

If a uniqueness violation or duplicate roster effect is observed, treat it as an integrity defect rather than retrying around it manually.

## Close diagnosis

If a Locked → Closed transition fails with incomplete participants, query non-withdrawn participants lacking `transfer_completions`. Each must be explicitly completed before close.

There is no bulk completion endpoint. Do not script direct database inserts as a substitute; each completion is an attributable privileged action.

## Snapshot and identity safety

Completion does not create `PlayerSnapshot` rows and does not rewrite snapshot history.

An incoming planning/source `KingdomPlayer` is not moved to the Alliance home Kingdom. The accepted roster action resolves the roster result under the accepted home-Kingdom identity contract, and the completion record points to that resulting roster entry.

Destination planning never changes neutral player identity.

## Readiness/blocker diagnosis

C2 rules remain unchanged before lock: readiness changes and blockers are Draft/Open only; entering blocked requires an active blocker; resolving the final blocker never auto-advances readiness; `ready`/`confirmed` cannot coexist with active blockers.

`confirmed` must never be interpreted as completion evidence. The authoritative real-world outcome is the `TransferCompletion` created through the completion action.

Blocker summary/details remain management-private and must not enter logs, audit metadata, outbox payloads, support diagnostics or member-facing payloads.

## Query shape

Transfer participant queries eager-load safe completion summary with the existing bounded relation set. Manager completion/readiness views additionally eager-load completion actor and resulting roster/player data. Avoid participant-loop relationship queries.

## Home-Kingdom recovery

For home-Kingdom drift, do not rewrite the plan's captured home context or completion records. Cancel the stale plan when lifecycle permits and create a deliberate replacement under the Alliance's current Kingdom. Completion fails closed on drift.

## Audit/outbox evidence

Material Slice D completion adds:

- `kingdoms.transfer_participant_completed` audit evidence; and
- matching internal transactional-outbox evidence.

Incoming/outgoing delegated roster actions continue their existing accepted roster event families when they materially mutate roster state. Staying completion produces only transfer-completion evidence because it performs no roster lifecycle mutation.

Completion event metadata may contain scoped transfer IDs, direction and resulting roster-entry ID. It must not contain private manager notes or blocker text.

`kingdoms.*` remains excluded from external webhook delivery.

## Deferred operations

Slice D performs no inferred eligibility/readiness, transfer-resource/pass optimization, bulk completion, automated stay/leave decisions, automated in-game transfer execution, player scoring/ranking, public/cross-alliance transfer workflow, or external game-data ingestion.

`KINGDOMS-002` remains **In progress** pending whole-increment hardening and acceptance / `K2-P6`.
