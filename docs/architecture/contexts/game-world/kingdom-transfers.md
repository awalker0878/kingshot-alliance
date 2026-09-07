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

## Read-model boundary

Read models may compose `TransferSelfEligibilityQuery` or other typed KingdomTransfers projections after authorization. They may render requirement/outcome/next-action information but must not calculate substitute game rules or persist a second transfer truth store.

## Fresh deployment

No compatibility aliases, legacy planning `TransferGroup`, dual reads/writes, migration backfills or schema shims are retained. The database is treated as fresh deployment state.
