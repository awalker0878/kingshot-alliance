# GameWorld — KingdomTransfers

Status: Current — Architecture V3 — 2026-09-07

Implementation: `app/Contexts/GameWorld/KingdomTransfers`

KingdomTransfers is the GameWorld owner for sourced Kingdom Transfer truth, Alliance transfer-planning commitments, participant workflow and deterministic eligibility. It is not a generic workflow context and does not belong under `app/Workflows`.

## Owned state

KingdomTransfers owns:

- Transfer Plans, participants, readiness transitions, blockers and completions;
- Alliance planning Transfer Cohorts;
- Transfer Windows/phase boundaries;
- official window-scoped Transfer Groups and Kingdom membership revisions;
- target Kingdom condition observations: Power Cap, classification, Hero Generation, Truegold and age threshold;
- target Kingdom capacity observations: Ordinary Invite/Open use and Special Invite inventory;
- transfer-specific Governor observations;
- Alliance capacity reservations and invitation allocations;
- provenance/freshness/conflict rules;
- deterministic eligibility and capacity projections;
- owner audit/outbox events;
- Transfer Evidence destination receipts/idempotency.

## Cross-context boundary

KingdomTransfers references Alliance, Player, Kingdom and Evidence identities but does not take ownership of those aggregates.

Cross-context effects use explicit owner Actions/value objects/scalar IDs. Completion may invoke Membership/Player owner Actions; Evidence may invoke KingdomTransfers destination Actions. No foreign Eloquent model is passed across context boundaries.

`Intelligence/Evidence` owns screenshot storage, OCR/provider attempts, classification/extraction, review revisions, duplicate decisions, commit attempts and retention. KingdomTransfers owns every accepted transfer fact after the scalar handoff.

## Terminology boundary

**Transfer Cohort** is internal Alliance coordination. **Transfer Group** is only the official KingShot event grouping. Official group membership is Transfer-Window-scoped; there is no timeless `Kingdom.transfer_group` attribute.

## Observation model

Game/domain facts are append-only observations, not mutable current-truth columns.

Participant observations include Power, Hero Generation, Truegold, character age difference, cooldown, target character count, Transfer Score, pass counts, invitation status, resource-protection verification and final in-game rules verification.

Target conditions and target capacity have separate append-only aggregates because they are target/window facts shared across participants.

Current selection is authority/freshness/conflict aware. Missing/stale/conflicting/non-authoritative facts cannot become `eligible_now`.

## Eligibility boundary

`TransferEligibilityEvaluator` is the single game-eligibility rule implementation. It accepts typed already-scoped inputs and returns a structured assessment. Controllers, Vue, Assistant and generic read models must not reproduce eligibility rules.

The evaluator covers:

- phase;
- official group compatibility;
- Hero Generation;
- Truegold;
- character age threshold;
- 25-day cooldown;
- four-character target limit;
- Power Cap/invitation path;
- target total/invite/open capacity;
- Special Invite inventory where applicable;
- Transfer Pass sufficiency from observed required count;
- Storehouse resource-protection pre-flight;
- final in-game rules verification.

Required Transfer Passes are observed. No Transfer Score → Pass formula is encoded.

Readiness remains a separate Alliance workflow state.

## Capacity ownership and projection

`TransferOfficialRulebook` owns version-bounded official public constants. Target capacity observations own current game usage/inventory. `TransferCapacityPlanningQuery` composes those facts with Alliance planning commitments.

Capacity reservations and invitation allocations are **planning intent**, not game truth.

Reservation writes serialize competing commitments and reject known total/bucket oversubscription. Special Invite allocations additionally require authoritative Ordinary classification and current inventory; Leading targets cannot consume Special Invite allocations.

### Reconciliation rule

Future planning states (`planned`/`reserved`) always reduce projected remaining capacity.

Finalized commitments continue reducing projected capacity only until a newer authoritative capacity observation is at or after the commitment update. Then the observed game state supersedes the planning subtraction. This prevents both premature slot reuse and permanent double counting.

Withdrawal releases/cancels consuming commitments inside the readiness transaction. Completion finalizes consuming commitments inside the completion transaction.

## Resource-protection boundary

`resource_protection_verified` models a material transfer consequence, not a fabricated game prohibition. False is actionable and may yield `eligible_with_action`; missing/stale/conflicting verification yields `needs_verification`. It is intentionally outside the hard-blocker set.

## Evidence boundary

Five explicit Transfer Evidence destination Actions are supported:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Target Kingdom rules are schema v2 and can carry only reviewed fixture-proven fields among target number, Power Cap, classification, Hero Generation, Truegold and age threshold.

Every destination Action:

1. reacquires current actor/Alliance authority;
2. resolves/locks current Plan/participant/window/target scope;
3. validates reviewed scope/provenance;
4. checks stable owner receipt/idempotency;
5. delegates to owner writers;
6. appends owner history/audit/outbox;
7. returns only scalar receipt data.

Material scope drift requires re-review. No Evidence schema can synthesize `in_game_rules_verified=true`.

## Shared owner writers

Owner Actions and Evidence destination Actions share internal writers after their respective authorization boundaries:

- `TransferObservationWriter` — typed observation validation, target requirements, freshness boundaries, deterministic identity and append-only persistence;
- `TransferKingdomConditionWriter` — target resolution, Power/classification/Hero/Truegold/age validation, correction rules and condition history;
- `TransferGroupWriter` — official-group membership, revision/supersession and conflict rules.

Capacity observation and planning commitment Actions remain explicit owner Actions because their concurrency/oversubscription semantics are distinct.

## Idempotency/concurrency

Planning identity resolution owns its transaction and locks the active source Kingdom shared before current canonical Player identity. Conflicting game IDs reject without foreign Player locks; reconciled aliases require explicit participant replacement. Transfer placement guards remain in the planning adapter, while current identity/history writes remain with PersistPlayerIdentity. [ADR-0034](../../adr/0034-current-stable-player-identity-and-registration.md) defines current identity conflicts and registration preconditions.

Completion discovers scoped participant routing and acquires its current Alliance authority followed by shared home/destination Kingdom locks in sorted ID order before locking the canonical target Player. It revalidates the locked participant's routing before new handoff. Existing completion is an idempotent return even if the outgoing destination has since archived; new movement still requires an active destination. Transfer authority stabilizes the actor's active membership under the Alliance barrier and reads actor identity without taking an early Player lock. Each roster handoff owner locks its current Player before its roster row. [ADR-0033](../../adr/0033-transfer-kingdom-and-player-lock-order.md) records the lifecycle ordering and verification contract.

- observation writes use deterministic fingerprints;
- Evidence commit uses stable destination receipt keys;
- official-group revision is serialized per window;
- target condition/capacity histories are append-only;
- reservation/allocation writes lock participant/current commitment and competing capacity/inventory rows;
- completion/withdrawal reconcile planning commitments in the same database transaction as the workflow mutation;
- duplicate retries do not append owner truth twice.

## Authorization

Every external mutation is behind an owner Action. HTTP password confirmation is additional UX/security hardening, not a replacement for application authorization.

The concrete actor/Alliance/Plan/window/participant/target scope is re-resolved at mutation time. Foreign IDs must not become a cross-Alliance existence oracle.

## Bounded workflow history

Readiness responses contain complete SQL counts for active blockers, resolved blockers and readiness transitions, not embedded historical collections. `TransferWorkflowHistoryQuery` provides separate 25-record keyset pages. Every request rechecks current Transfer View authority and the concrete Alliance/Plan/participant before reading rows. History predicates include all three IDs even when a malformed row references a participant from another scope. Cursors are encrypted and bound to the concrete history and blocker state; a token is not authorization.

Active and resolved blockers have independent navigation so newer resolved records cannot hide an older current blocker. Histories use `(created_at, id)` descending, with one bounded look-ahead row; continuation does not require the boundary row still to exist. Counts describe the current matching relation, not the size of the displayed page. New writes are visible after first-page refresh; pagination is not a cross-request snapshot transaction.

The canonical fresh schema requires dated workflow records and indexes each scope/order predicate. No compatibility row limit, backfill or duplicate history representation is retained. Readiness transitions and blocker writes remain with their existing Actions; pagination does not evaluate or mutate game eligibility. The owner-local frontend loads a history only when opened, retains unrelated form drafts, rejects malformed responses, and discards superseded requests after scope/filter changes. Participant navigation follows the separate contract below; dashboard and remaining catalogue expansion stays tracked under HARD-095.

## Bounded participant workspaces

The overview, management, readiness and completion workspaces use `TransferParticipantQuery::page` rather than an embedded complete participant array. Each request verifies current Transfer permission and exact Alliance/Plan ownership before selecting at most 25 participants and one look-ahead row. Encrypted cursors bind the actor, Alliance, Plan, withdrawal view and required permission. The stable participant ID is the ordering key: changes to labels, readiness or direction cannot move a row across a cursor boundary, and continuation does not need the boundary row still to exist. The canonical fresh schema indexes the full scope/key and the active-only predicate separately.

`summary` returns complete current SQL counts for the same authorized view: direction, completion, confirmed-awaiting-completion and withdrawal. These are persisted workflow facts, not a second game-eligibility evaluation. Counts are not derived from the displayed page. Related cohorts, completions, reservations and invitations must match both the participant's Alliance and Plan; a malformed foreign reference is not a readable relationship. Full history counts retain their independent scoped predicates.

Only displayed participants are passed to the existing canonical eligibility composition. Readiness filters are explicitly page-local; continuation remains available even when a filter leaves the current page empty. The UI does not interpret a page's eligible or blocked count as the whole plan. Current summary and page data are separate current reads, not a long-lived snapshot across browser requests.

The owner-local pager retains the displayed page and edited drafts on a failed request, exposes retry, cancels obsolete requests and resets on scope changes. `useTransferDrafts` retains only current-page defaults plus deliberately edited drafts; clean departed rows are discarded. Changing Alliance/Plan discards old-scope drafts. Observation-history requests are cancelled when their participant page leaves the view. All four producers/consumers use the single PageSlice response; no old array alias is retained. This extends [ADR-0001](../../adr/0001-composed-management-reads-and-scoped-cursors.md), not a competing pagination framework.

Dashboard-wide evaluation and the management catalogues/selectors are separate remaining HARD-095 consumers until their own bounded contracts are implemented and verified. A bounded participant page does not claim those remaining queries are bounded.

## Read-model boundary

Read models may compose `TransferSelfEligibilityQuery` or other typed KingdomTransfers projections after authorization. They may render requirement/outcome/next-action information but must not calculate substitute game rules or persist a second transfer truth store.

## Fresh deployment

No compatibility aliases, legacy planning `TransferGroup`, dual reads/writes, migration backfills or schema shims are retained. The database is treated as fresh deployment state.

Eligibility and screenshot preview load only bounded factual witnesses for the requested participants and source/target Kingdoms, preserving current conflicts and authoritative provenance. Capacity planning counts consuming commitments in SQL against the latest authoritative capacity observation. The Readiness page separately loads complete observation history in current-authorized 25-record pages, with scoped continuation and retry. Recruitment campaign evidence and active-blocker totals are SQL counts over all matching records.

Member capability profiles resolve the active participant by current-authorized Alliance, Plan and Player at the KingdomTransfers query boundary. They do not load the plan-wide participant collection or unrelated relationship graphs to locate a single Governor. The selected row is evaluated by the existing canonical eligibility query; a missing or withdrawn row is absence, never a fallback to another participant.

Self-transfer assessment, including Assistant answers, delegates current persisted facts to TransferEligibilityQuery after current authorization and exact actor/plan selection. It uses the same bounded conflict witnesses and provenance as management, not a second evaluator composition. The self response counts complete observation history in SQL without hydrating it or equating the witness set with the total. [ADR-0044](../../adr/0044-canonical-self-transfer-eligibility.md) records this authority boundary; hypothetical evidence preview remains explicitly distinct from current assessment.

## Bounded verification overview

The dashboard, Assistant and Officer Brief consume the owner-local `TransferVerificationPreviewQuery`, not the eager participant collection. It evaluates at most 25 active participants with the canonical eligibility query, retains complete SQL totals/manual blockers, and makes unassessed coverage explicit. An incomplete preview is never verified. Full current per-participant checks remain reachable through readiness pages; no derived status becomes a write authority. [ADR-0051](../../adr/0051-bounded-transfer-verification-overviews.md) defines counts, coverage, dependency direction and operational limits.

## Management read ownership

The overview and management GET adapters and transport projection live in `ReadModels/TransferManagement`, following [ADR-0001](../../adr/0001-composed-management-reads-and-scoped-cursors.md). Every projection rechecks the current Transfer View or Manage authority before loading plan data. The overview no longer loads a complete unused cohort catalogue; it projects only cohorts belonging to the bounded visible participant page. The existing current/mutable-plan queries remain the authoritative selection rules. Plan writes stay with the context Actions and thin TransferPlanController; no Context imports this ReadModel and no compatibility controller aliases remain. Remaining management catalogue/selector bounds are tracked by HARD-095 rather than described as complete.
