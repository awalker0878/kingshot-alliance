# Kingdom Transfer Planning reference

Status: Current — 2026-08-26

Owner: `GameWorld/KingdomTransfers`

Product contract: [`../product/kingdom-transfer-planning.md`](../product/kingdom-transfer-planning.md)

Transfer Evidence product contract: [`../product/screenshot-intake-transfer-evidence.md`](../product/screenshot-intake-transfer-evidence.md)

Architecture: [`../architecture/contexts/game-world/kingdom-transfers.md`](../architecture/contexts/game-world/kingdom-transfers.md)

## Boundary

The HTTP surface is Alliance-scoped and uses the active Player established by `alliance.context`. Read routes require the transfer view boundary; writes require `kingdom_transfer.manage`, password confirmation, and concrete owner-scope authorization inside the application Action.

Frontend permission flags control affordances only. They are not authorization evidence.

`GameWorld/KingdomTransfers` owns accepted Transfer observations, official groups, target conditions, freshness/conflict rules and eligibility. `Intelligence/Evidence` may coordinate reviewed screenshot handoffs but never writes these owner tables directly and never passes an Eloquent model into KingdomTransfers.

## Read routes

| Method | Route | Purpose |
| --- | --- | --- |
| `GET` | `/alliance/transfers` | Transfer plan overview. |
| `GET` | `/alliance/transfers/manage` | Transfer Window, official game-fact, cohort and participant management. |
| `GET` | `/alliance/transfers/readiness` | Decision-first participant eligibility, independent workflow readiness, and the entry point for participant in-game Evidence. |
| `GET` | `/alliance/transfers/completion` | Final transfer outcome workflow. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence` | Lazy participant-scoped Screenshot Intake summary and schema registry contract. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/image` | Stream one authorized private retained screenshot. |
| `GET` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/preview` | Derive current-versus-reviewed eligibility through the owner evaluator without persisting hypothetical state. |

The readiness response composes, for each participant, the selected Transfer Window, target Kingdom, official phase, official Transfer Group, applicable target condition observation, selected Governor observations, structured eligibility assessment, planning readiness, cohort, manual blockers and relevant history. Material decision facts include their source type, source reference and observation time; the UI does not present an official Transfer Group, target Power Cap or Transfer Score without the provenance context needed to explain the conclusion.

Transfer Evidence itself is loaded lazily when the participant's **Add in-game evidence** surface is opened so normal readiness composition remains bounded rather than adding per-participant Evidence queries.

## Transfer Window writes

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/windows` | Record a Transfer Window and explicit phase boundaries. |
| `PATCH` | `/alliance/transfers/windows/{window}` | Correct a window before its Pre-Transfer phase starts. |
| `POST` | `/alliance/transfers/windows/{window}/official-groups` | Record/revise an official Transfer Group and its window-scoped Kingdom membership. |
| `POST` | `/alliance/transfers/windows/{window}/conditions` | Record a target Kingdom condition observation such as Power Cap/classification. |

Window/group/condition writes require explicit provenance. Official game facts are not accepted as timeless Kingdom attributes.

### Transfer Window fields

Window input carries a label and strictly increasing UTC boundaries for:

- `pre_transfer_starts_at`;
- `invitational_starts_at`;
- `transfer_opens_at`;
- `ends_at`.

It also carries source type/reference, `observed_at`, and optional evidence reference where available. A window is immutable once its Pre-Transfer phase has begun.

### Official Transfer Group fields

An official Transfer Group record includes its window, official label, Kingdom membership, source/reference, `observed_at`, and an optional `evidence_id`. Re-recording identical evidence is idempotent. A correction creates a new revision and supersedes the previous current revision; a Kingdom cannot belong to two current official groups in the same window.

When `source_type=evidence`, `evidence_id` is mandatory and must resolve through the Intelligence/Evidence owner contract to the same Alliance with a latest approved review. A foreign, missing, deleted-before-approval, or unapproved Evidence reference is rejected before the official Group fact is recorded.

### Target condition fields

Target Kingdom condition observations are append-only and window/Kingdom-scoped. Supported typed facts include the observed Power Cap and Kingdom classification. Corrections preserve prior records rather than rewriting history. An optional Evidence reference follows the same owner-scope and approval rules described below.

## Plan and cohort writes

Existing plan, participant, readiness, blocker and completion routes remain supported. The previous Alliance planning `TransferGroup` concept has been renamed cleanly to **Transfer Cohort**; no compatibility route or alias is retained.

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/{plan}/cohorts` | Create a planning cohort. |
| `PATCH` | `/alliance/transfers/{plan}/cohorts/{cohort}` | Update a planning cohort. |
| `POST` | `/alliance/transfers/{plan}/cohorts/{cohort}/archive` | Archive a planning cohort. |
| `PATCH` | `/alliance/transfers/{plan}/participants/{participant}/cohort` | Assign/unassign a participant cohort. |

## Governor observation write

`POST /alliance/transfers/{plan}/participants/{participant}/observations`

The endpoint records one append-only transfer observation. It does not set eligibility.

Observation input is typed by observation kind and may carry:

- target Kingdom when the fact is target-specific;
- numeric, text or boolean value according to kind;
- source type and source reference;
- `observed_at`;
- `valid_until` for mutable Governor facts;
- optional evidence reference;
- optional explanatory details.

Supported transfer observation kinds cover Governor Power, Transfer Score, available Transfer Passes, observed required Transfer Passes, invitation status and the explicit in-game verification gate used for eligibility rules not safely modeled from public authoritative evidence.

The write fingerprint is derived from owner scope plus the normalized observation payload. Retrying the identical record is a no-op and returns the existing observation rather than creating duplicate history.

## Screenshot Intake: Transfer Evidence writes

Transfer participant Screenshot Intake is a separate authorized mutation path from the manual observation form. It is the only participant UI that may produce `source_type=evidence` because it owns the approved Evidence reference and commit handshake.

| Method | Route | Purpose |
| --- | --- | --- |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence` | Upload a private screenshot with an expected schema and start independent classification. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/review` | Save an immutable human review revision. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/resolve-duplicate` | Resolve a semantic duplicate with an audited justification. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/reviews/{review}/commit` | Execute the schema-specific owner handoff and record only the returned Evidence receipt. |
| `POST` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}/retry` | Retry terminal failed Evidence processing. |
| `DELETE` | `/alliance/transfers/{plan}/participants/{participant}/evidence/{evidence}` | Redact/delete Evidence-owned source material while preserving accepted owner history. |

All of these writes require current Transfer management authority and recent password confirmation. Evidence also re-resolves its participant scope at the application boundary. Commit then re-enters KingdomTransfers and reacquires current authority again in the owner transaction.

The five destination Actions are:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

They share `TransferEvidenceDestinationSupport` for current-scope locking, provenance validation, stable receipt lookup/creation and audit/outbox behavior. The actions reuse the existing internal observation/condition/group writers rather than duplicating owner authorization.

A reviewed screenshot is not silently retargeted. The destination compares the approved Transfer Window/participant/target snapshot with current owner state. A material change rejects a new destination write and requires a new/revalidated review.

### Atomic score/pass semantics

A score/pass screenshot is one reviewed meaning. `RecordTransferScorePassEvidence` records Transfer Score, passes available and observed passes required inside one outer database transaction. Any failed typed value, scope check, provenance check or owner invariant rolls the entire handoff back.

### Destination receipt/idempotency

Every approved Transfer review derives a stable destination idempotency key. `transfer_evidence_receipts` enforces uniqueness in the owner context. A retry after owner commit but before Evidence acknowledgement returns the existing receipt and creates no duplicate observation history.

This is separate from Evidence semantic-duplicate detection between different screenshots.

## Source types

| Source | Eligibility authority |
| --- | --- |
| `official_publication` | May satisfy an authoritative published game fact. |
| `in_game` | May satisfy a fact observed directly in KingShot. |
| `evidence` | May satisfy a fact only when `evidence_id` resolves through Intelligence/Evidence to the same Alliance and its latest relevant review is approved. |
| `manager_note` | Planning context only; not authoritative eligibility truth. |
| `community` | Discovery/context only; not authoritative eligibility truth. |

A source reference is mandatory for recorded sourced facts. `source_type=evidence` additionally requires an `evidence_id` validated through the Intelligence/Evidence owner contract; a foreign or unapproved Evidence record is rejected. Optional Evidence attachments on other source types are also same-Alliance checked. KingdomTransfers consumes only the owner-side Evidence reference contract and never loads or mutates Evidence models directly.

Manual transfer forms do not expose `evidence` as a selectable source. Evidence-backed observations/groups/conditions must arrive through the Screenshot Intake handoff so the approved review and stable receipt exist.

Mutable Governor observations without an explicit validity boundary cannot silently become current eligibility truth.

## Eligibility response contract

Eligibility is a deterministic response, never a persisted boolean. The server emits:

- overall `outcome`;
- `evaluated_at`;
- `primary_next_action` when applicable;
- ordered `requirements`.

Each requirement contains:

- requirement key;
- state: `met`, `unmet`, `unknown`, `stale`, `conflicting`, or `not_applicable`;
- actual and required values where meaningful;
- human-readable explanation/next action;
- source/reference and observation time for material evidence.

The participant summary also exposes provenance for the selected official Transfer Group, target Kingdom condition/Power Cap, and Transfer Score observation so the decision can be traced without opening a separate management page.

Overall outcomes are `eligible_now`, `eligible_with_action`, `blocked`, `needs_verification`, `not_open_yet`, `window_closed`, or `not_applicable`.

`eligible_now` is impossible when a material requirement is missing, stale, conflicting, non-authoritative or otherwise unverified.

The Transfer Evidence preview calls the same `TransferEligibilityEvaluator` used by current reads. It substitutes only the reviewed candidate facts in memory. No v1 Evidence schema can set `in_game_rules_verified`, so a missing verification gate remains missing in preview and after commit.

## Transfer Pass rule boundary

The application does **not** invent a public Transfer Pass formula. Where KingShot/public authoritative evidence does not expose a trustworthy calculation, the required-pass value is recorded as an observed sourced fact. The evaluator may compare fresh authoritative available/required observations, but it does not extrapolate an undocumented formula.

The `transfer_score_passes` screenshot schema requires the displayed Transfer Score, displayed available Passes and displayed required Passes independently. Missing required Passes cannot be filled from Transfer Score or a nearby unrelated number.

## Error semantics

- invalid typed input → validation error;
- wrong Alliance/plan/window/participant scope → authorization/not-found boundary without cross-scope disclosure;
- Evidence source without a same-Alliance approved Evidence review → validation error;
- optional cross-Alliance Evidence attachment on another source type → validation error;
- withdrawn participant → observation/evidence mutation rejected;
- material target/window scope changed since Evidence review → destination commit rejected for re-review;
- mutable observation without explicit validity → review/write rejected or historical/unknown according to the owner contract;
- stale/conflicting/non-authoritative facts → successful read with `needs_verification`, never optimistic eligibility;
- duplicate owner retry → idempotent receipt/no duplicate row;
- semantic duplicate screenshot → Evidence review blocked until explicit supported resolution.

## Query budget

Read composition must remain bounded by relation type rather than participant count. Eligibility evaluation operates on preloaded/typed snapshots and must not issue per-requirement database queries from the evaluator or Vue layer.

Transfer Evidence summaries are intentionally lazy per opened participant panel. The normal readiness response does not eagerly query each participant's screenshot history.