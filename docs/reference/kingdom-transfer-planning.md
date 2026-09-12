# Kingdom Transfer Planning reference

Status: Current — 2026-09-07

Owner: `GameWorld/KingdomTransfers`

Product contract: [`../product/kingdom-transfer-planning.md`](../product/kingdom-transfer-planning.md)  
Official-rule source matrix: [`../product/kingdom-transfer-official-rules-source-matrix.md`](../product/kingdom-transfer-official-rules-source-matrix.md)  
Transfer Evidence contract: [`../product/screenshot-intake-transfer-evidence.md`](../product/screenshot-intake-transfer-evidence.md)  
Architecture: [`../architecture/contexts/game-world/kingdom-transfers.md`](../architecture/contexts/game-world/kingdom-transfers.md)

## Boundary

All HTTP reads are Alliance-scoped through `alliance.context`. Transfer writes require current `kingdom_transfer.manage` authority and recent password confirmation. Application Actions re-resolve current actor/Alliance and concrete plan/window/participant scope inside their transaction.

Frontend capability flags control affordances only.

KingdomTransfers owns accepted transfer facts, planning commitments and eligibility. `Intelligence/Evidence` owns screenshot provenance/review/retention and hands reviewed scalar meaning to dedicated KingdomTransfers Actions.

## Read routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/alliance/transfers` | Current Transfer Plan overview. |
| `GET` | `/alliance/transfers/manage` | Window, official facts, capacity, cohorts and participant management. |
| `GET` | `/alliance/transfers/readiness` | Server-authoritative eligibility plus independent Alliance readiness. |
| `GET` | `/alliance/transfers/completion` | Final outcome workflow. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/observations` | Complete sourced observation history in 25-record pages. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/blockers?state=active` | Active or resolved (`state=resolved`) planning blockers, independently paged. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/readiness-history` | Complete readiness-transition history in 25-record pages. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence` | Lazy participant Transfer Evidence summary/schema registry. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/image` | Authorized private image stream. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/preview` | Current-versus-reviewed evaluator preview. |

The readiness response exposes `activeBlockerCount`, `resolvedBlockerCount` and `readinessTransitionCount` as complete current counts rather than embedding child histories. Workflow history responses use `items`, `nextCursor`, `hasMore`, `pageSize` and `isFirstPage`; `cursor` is an opaque string, limited to 4,096 characters. It is bound to Alliance, Plan, participant, history kind and blocker state. Invalid or mismatched tokens fail validation; every page requires current authority. Evidence and workflow histories load only when their panel is opened. Full participant-set bounding remains tracked in HARD-095 and is not implied by the per-history page size.

## Official-fact writes

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/windows` | Record a sourced Transfer Window. |
| `PATCH` | `/alliance/transfers/windows/{window}` | Correct an eligible window before its immutable boundary. |
| `POST` | `/alliance/transfers/windows/{window}/official-groups` | Record/revise official Transfer Group membership. |
| `POST` | `/alliance/transfers/windows/{window}/conditions` | Append target Kingdom rule/condition facts. |
| `POST` | `/alliance/transfers/windows/{window}/capacity` | Append observed target slot usage/Special Invite inventory. |

### Target condition payload

Supported typed facts are:

- `kingdom_number`;
- optional `power_cap`;
- optional classification;
- optional `hero_generation`;
- optional `truegold_level`;
- optional `character_age_threshold_days`;
- source type/reference;
- `observed_at`;
- correction marker;
- optional Evidence reference where the owner path permits it.

Conditions are append-only. Corrections preserve prior rows. Current selection is authoritative-source aware rather than naive last-write-wins.

### Capacity payload

Capacity observations carry:

- target Kingdom;
- optional Ordinary Invites used;
- optional Transfer Opens used;
- optional Special Invites available, bounded `0..3`;
- source/reference;
- `observed_at`;
- correction marker;
- optional Evidence reference.

Official totals are classification-derived from `TransferOfficialRulebook`; observed use/inventory is never manufactured from those totals.

## Plan, cohort and participant writes

The existing Plan lifecycle is Draft → Open → Locked → Closed, with Cancelled as terminal. Alliance planning **Transfer Cohorts** remain distinct from official Transfer Groups.

Participant mutation routes include create/update, cohort assignment, withdrawal, completion, readiness transitions and manual blocker management.

## Governor observation write

`POST /alliance/transfers/{plan}/participants/{participant}/observations`

Supported observation kinds:

- `governor_power`;
- `hero_generation`;
- `truegold_level`;
- `character_age_over_target_days`;
- `transfer_cooldown_remaining_days`;
- `target_existing_character_count`;
- `transfer_score`;
- `transfer_passes_available`;
- `transfer_passes_required`;
- `invitation_status`;
- `resource_protection_verified`;
- `in_game_rules_verified`.

Numeric/text/boolean storage is chosen by the enum contract. Boolean observations and reviewed-evidence values use the shared localized Yes/No labels. Missing, unknown and unverified values remain distinct states; presentation does not infer a boolean from missing data. Target-specific observations must match the participant's current target. Mutable current-use facts require an explicit `valid_until` boundary.

Manual forms do not expose `source_type=evidence`; reviewed Evidence commits own that provenance path.

## Capacity reservation write

`PATCH /alliance/transfers/{plan}/participants/{participant}/capacity-reservation`

Payload:

- `bucket`: `ordinary_invite` or `transfer_open`;
- `state`: `planned`, `reserved`, `confirmed`, `released`, or `failed`;
- optional notes.

A consuming reservation is allowed only for an active incoming/outgoing participant with a target and verified authoritative capacity. The Action locks current competing reservations and rejects total/bucket oversubscription.

These rows are Alliance planning intent, not game reservations.

## Invitation allocation write

`PATCH /alliance/transfers/{plan}/participants/{participant}/invitation-allocation`

Payload:

- kind: `ordinary` or `special`;
- state: `requested`, `reserved`, `issued`, `accepted`, `declined`, or `cancelled`;
- optional notes.

A consuming Special Invite allocation requires an authoritative Ordinary target classification, current Special Invite inventory and remaining inventory after competing allocations. Leading Kingdoms cannot create a consuming Special Invite allocation.

Allocation state is planning workflow and never replaces the independently sourced `invitation_status` used by eligibility.

## Capacity projection semantics

`TransferCapacityPlanningQuery` composes, per target Kingdom:

- official total/invite/open capacity from classification;
- observed Ordinary Invite/Open use and Special Invite inventory;
- Alliance planned Ordinary/Open reservations;
- Alliance planned Special Invite allocations;
- observed remaining capacity;
- projected remaining capacity after Alliance planning.

Planned/reserved commitments always reduce the projection. Finalized commitments (`confirmed` capacity; `issued`/`accepted` Special Invites) continue to reduce it until a newer authoritative capacity observation is at or after the commitment update. At that point the observation supersedes the planning subtraction so the same consumed slot/invite is not counted twice.

## Completion and withdrawal reconciliation

Withdrawal happens through readiness transition and, atomically:

- marks the participant withdrawn;
- releases consuming capacity reservations;
- cancels consuming invitation allocations;
- records audit/outbox counts.

Completion is allowed only after the Plan is Locked and the participant is explicitly Confirmed. In the same completion transaction it:

- records the final TransferCompletion;
- performs existing roster/Player owner handoffs;
- finalizes consuming capacity reservations to `confirmed`;
- finalizes consuming invitation allocations to `accepted` as Alliance planning state;
- emits completion audit/outbox metadata.

No completion mutation rewrites prior sourced eligibility observations.

## Eligibility response

Eligibility is derived by `TransferEligibilityEvaluator`; no persisted `eligible` column exists.

Requirement keys:

- `window_phase`;
- `transfer_group`;
- `hero_generation`;
- `truegold_level`;
- `character_age`;
- `transfer_cooldown`;
- `target_character_limit`;
- `power_cap`;
- `invitation`;
- `target_capacity`;
- `invitation_capacity`;
- `transfer_open_capacity`;
- `transfer_passes`;
- `resource_protection`;
- `in_game_rules`.

Requirement states are `met`, `unmet`, `unknown`, `stale`, `conflicting`, and `not_applicable`.

Outcomes are `eligible_now`, `eligible_with_action`, `blocked`, `needs_verification`, `not_open_yet`, `window_closed`, and `not_applicable`.

Any material unknown/stale/conflict prevents `eligible_now`.

## Current official rule constants

`TransferOfficialRulebook` is the versioned application rule boundary for sourced public constants:

- Ordinary: total 55, Ordinary Invite 35, Transfer Opens 20;
- Leading: total 30, Ordinary Invite 20, Transfer Opens 10;
- Special Invite maximum 3;
- maximum four characters in a Kingdom;
- Transfer Pass observed required range 1–50.

Target-specific Hero/Truegold/age/capacity usage remains sourced observation truth rather than anonymous constants.

## Transfer Pass boundary

The exact required-pass formula is not public enough to encode safely. `transfer_passes_required` is therefore a current observed fact. Transfer Score is not used to synthesize required Passes.

## Resource protection boundary

`resource_protection_verified` is a pre-flight consequence check. False means actionable resource loss risk, not a fabricated game prohibition. Unknown/stale/conflicting resource verification prevents an optimistic `eligible_now` result.

## Transfer Evidence

Five explicit screenshot families are supported. Target Kingdom rules are schema v2 and may review fixture-proven:

- target Kingdom number;
- Power Cap;
- classification;
- Hero Generation;
- Truegold level;
- character-age threshold days.

The owner destination Actions remain:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Preview calls the same evaluator as live reads and persists nothing. Commit revalidates current scope and records stable owner receipts. No Evidence schema can create `in_game_rules_verified=true`.

## Source authority

| Source | Current eligibility authority |
| --- | --- |
| `official_publication` | Yes, for facts explicitly supported by the publication/version. |
| `in_game` | Yes, for directly observed current facts. |
| `evidence` | Yes only through an approved same-Alliance reviewed Evidence handoff. |
| `manager_note` | No; planning context only. |
| `community` | No; discovery/context only. |

## Error/fail-closed semantics

- invalid typed input → validation error;
- wrong owner scope → authorization/not-found boundary without cross-Alliance disclosure;
- stale/missing/conflicting/non-authoritative facts → successful read with `needs_verification`;
- no verified slot/invite inventory → consuming planning mutation rejected;
- scope drift after Evidence review → re-review required;
- duplicate Evidence owner retry → stable receipt/no duplicate owner facts;
- unsupported required-pass formula → observe in-game value instead of calculating it.

## Release checks

Final readiness requires clean database migration, Pint/PHPStan, frontend lint/format/type/build, KingdomTransfers/Evidence V3 tests, architecture/intelligence/visual workflows, CodeQL/Dependency Review, bounded-query and cross-Alliance isolation coverage, plus documentation/source-matrix reconciliation.

Eligibility and screenshot preview load only bounded factual witnesses for the requested participants and source/target Kingdoms, preserving current conflicts and authoritative provenance. Capacity planning counts consuming commitments in SQL against the latest authoritative capacity observation. The Readiness page separately loads complete observation history in current-authorized 25-record pages, with scoped continuation and retry. Recruitment campaign evidence and active-blocker totals are SQL counts over all matching records.

## Participant page response

The four Transfer workspaces return `participants` as a PageSlice: `items`, `nextCursor`, `hasMore`, `pageSize` and `isFirstPage`. Requests supply the optional `participant_cursor`. `participantSummary` contains complete `total`, `incoming`, `outgoing`, `staying`, `completed`, `confirmed` and `withdrawn` workflow counts for the same view, or null without a selected plan. Overview excludes withdrawn participants; management, readiness and outcomes include them. The current actor and required permission are checked on every page and summary read.

A cursor applies only to its actor, Alliance, Plan, withdrawal view and permission. Cross-view tokens or malformed positions fail validation rather than starting a different result set. Current membership/rank revocation still denies access even with a previously issued token. The request size bound is 4,096 characters; page size is 25, with one database look-ahead row. Stable ID ordering intentionally does not regroup pages after a label or workflow-state edit. No previous array response or compatibility endpoint is retained. See [query ownership](../architecture/contexts/game-world/kingdom-transfers.md).

## Management choice response

`GET /alliance/transfers/manage/choices/{kind}` accepts `windows`, `coordinators` or `roster`. Optional fields are `q` (160 characters), `cursor` (4,096 characters) and `selected` (ULID). Coordinators/roster require `plan` (ULID) for the exact mutable scoped plan; windows reject a plan parameter. Actor and Alliance are never supplied by the client as authorization.

The JSON object has `page` (PageSlice of `{id,name}`), `total` (complete current match count), and `selected` (the separately authorized `{id,name}` or null). The selected row is not an extra page item and does not depend on search text. Page size is 25 and the SQL probe is 26; stable ID/high-water continuation remains valid without the boundary row. Current access is checked before data selection. Invalid cursor scope/shape returns validation failure, unavailable plans remain not-found, and revoked authority is forbidden. Responses send `Cache-Control: private, no-store`. All original mutation endpoints and their independent current checks remain authoritative.

The management workspace exposes independent 25-record pages for Transfer Windows, plans, official groups, condition/capacity history and planning cohorts. Counts cover the complete authorized collection. Select a plan from its catalogue to inspect retained closed or cancelled history; current owner state determines whether editing is available. Official-group Kingdom membership loads separately in pages of 25. Participant cohort assignment choices use the existing searched choice endpoint and current Context-owned compatibility, including off-page selected values. Failed page loads preserve drafts and offer retry.
