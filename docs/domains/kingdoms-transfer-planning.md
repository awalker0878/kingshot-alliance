# Kingdoms transfer planning

[← Kingdoms](kingdoms.md)

**Increment:** `KINGDOMS-002`  
**Current delivery:** Slice B / `K2-P2` candidate on validated Slice A  
**Slice A evidence:** [KINGDOMS-002 Slice A validation](../product/kingdoms-transfer-planning-slice-a-validation.md)

`KINGDOMS-002` is an alliance-owned planning workflow layered on the accepted `KINGDOMS-001` Kingdom/player/roster foundation. Slice B adds participant intent and destination planning without implementing groups, readiness, blockers, or transfer completion.

## Ownership and tenancy

`TransferPlan` and `TransferParticipant` are Kingdoms-domain tenant data owned by one Alliance. `Kingdom` and `KingdomPlayer` remain global neutral reference data only.

Every participant read and mutation is constrained by both the active Alliance and the selected transfer plan. Submitted plan, participant, roster, and membership IDs are re-resolved under that tenant boundary.

Sharing the same Kingdom or neutral player reference never grants another Alliance access to transfer intent, private manager notes, membership linkage, or destination planning.

## Transfer plan lifecycle

The validated Slice A lifecycle remains:

```text
draft → open → locked → closed
  └──────┬──────┘
         └────────→ cancelled
```

Participant changes are permitted only while the plan is `draft` or `open`. `locked`, `closed`, and `cancelled` plans are read-only for Slice B.

The plan captures immutable `home_kingdom_id`. If the Alliance's current Kingdom later differs, participant mutations fail closed. Cancellation remains the safe stale-plan recovery path.

## Participant directions

Slice B defines three explicit manual directions:

- `staying` — an existing active/tracked alliance roster player who is not planned to move;
- `outgoing` — an existing active/tracked alliance roster player who may move to another Kingdom; and
- `incoming` — a player planned to arrive in the plan home Kingdom, potentially before a site membership or alliance roster entry exists.

Direction is planning intent, not an automated recommendation or game-derived fact.

### Staying

A staying participant:

- must bind to an active/tracked roster entry in the active Alliance;
- uses the plan home Kingdom as source;
- has no transfer destination; and
- derives neutral player identity and optional membership linkage from the existing roster record.

### Outgoing

An outgoing participant:

- must bind to an active/tracked roster entry in the active Alliance;
- uses the plan home Kingdom as source;
- may have no destination while leadership is still deciding;
- may reference another active canonical Kingdom as destination; and
- cannot use the plan home Kingdom as its outgoing destination.

Changing a destination never changes the neutral `KingdomPlayer.kingdom_id`; destination is plan-scoped intent only.

### Incoming

An incoming participant:

- may exist without an alliance roster entry;
- may exist without a site membership;
- stores an observed player name as plan-scoped identity;
- may optionally link an active same-alliance membership;
- may optionally record a source Kingdom;
- always uses the plan home Kingdom as destination; and
- may resolve a neutral `KingdomPlayer` only when a source Kingdom and stable game-player ID are both supplied.

A display name alone never creates or merges neutral game identity. If no source + stable ID pair exists, the participant remains plan-scoped rather than guessing global identity.

## Identity continuity

Normal updates may refine planning without silently changing who the row represents:

- roster-bound participant rows cannot be switched to a different roster entry;
- incoming rows with a known stable game-player ID cannot replace it with another ID;
- incoming rows with a known source Kingdom cannot replace it with another source;
- a resolved neutral player cannot be replaced by another neutral player; and
- changing between incoming and roster-bound identity classes requires withdraw + recreate.

This preserves auditability while still allowing an initially unlinked incoming row to gain a source/stable identity later.

## Visibility and authorization

- member transfer view: `alliance.view`;
- management view: `kingdoms.manage`;
- participant create/update/withdraw: `kingdoms.manage` plus recent password confirmation.

Member payloads expose operationally safe participant direction/source/destination/linkage display only. Manager notes and private membership details are excluded.

Coordinator concepts do not exist in Slice B. A later coordinator assignment cannot become an authorization shortcut.

## Audit and outbox

Material participant changes emit attributable audit and internal-outbox events:

- `kingdoms.transfer_participant_created`;
- `kingdoms.transfer_participant_updated`; and
- `kingdoms.transfer_participant_withdrawn`.

Manager notes are not copied into audit/outbox metadata.

The existing integration boundary excludes all `kingdoms.*` events from generic external webhook fan-out. Slice B therefore creates no public webhook contract.

## Withdrawal and duplicates

Withdrawal is soft workflow history: the participant row remains with `withdrawn_at`.

Withdrawal retries are idempotent and do not duplicate audit/outbox evidence.

Within one plan:

- a roster entry can have at most one active transfer participant row; and
- an incoming neutral player with a resolved stable identity can have at most one active incoming row.

Display-name collisions remain allowed when stable identity is unknown.

## Explicit Slice B non-capabilities

Slice B does not implement:

- transfer groups or coordinators;
- readiness states or blocker tracking;
- transfer passes/tickets/resources or eligibility rules;
- automated stay/leave decisions or destination ranking;
- transfer execution or roster completion/handoff;
- transfer marketplace/public advertising;
- diplomacy/NAP intelligence;
- cross-alliance transfer visibility;
- scraping, OCR, bots, or undocumented game APIs;
- AI/punitive scoring or recommendations; or
- public Kingdoms API/webhook contracts.
