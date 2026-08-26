# GameWorld — KingdomTransfers

Status: Current — Architecture V3

Implementation target: `app/Contexts/GameWorld/KingdomTransfers`

KingdomTransfers owns the domain state and rules for planning and executing Player/Kingdom transfer behavior, including sourced Kingdom Transfer game-planning truth and deterministic eligibility assessment.

## Owned state

KingdomTransfers owns:

- Transfer Plans, participants, readiness transitions, manual blockers and completion state;
- Alliance planning **Transfer Cohorts**;
- Transfer Windows and explicit official phase boundaries;
- official window-scoped Transfer Groups and their Kingdom membership;
- window/target-scoped Kingdom condition observations such as Power Cap and Kingdom classification;
- transfer-specific Governor observations including Power, Transfer Score, available/required Transfer Passes, invitation status and in-game eligibility verification;
- provenance/freshness/conflict semantics for transfer observations;
- deterministic transfer eligibility requirements and next-action calculation;
- transfer audit/outbox events and transfer-specific write invariants;
- accepted Transfer Evidence destination receipts and destination idempotency.

## Boundary

A transfer may reference Player, Kingdom, Alliance and Evidence identifiers, but the capability does not take ownership of those aggregates. It does not mutate Alliance membership, Kingdom identity, Player identity/progression or Intelligence/Evidence records directly.

Cross-context effects use explicit owner Actions, immutable references/snapshots, scalar identifiers, value objects or composed reads. Evidence may support a transfer observation, but Intelligence/Evidence remains the owner of the source artifact, OCR/classification/extraction/review lifecycle, duplicate decisions, commit-attempt lifecycle and retention. KingdomTransfers validates an Evidence reference only through the Intelligence/Evidence owner lookup contract. KingdomTransfers never loads or mutates Evidence models directly.

No foreign Eloquent model crosses the Evidence/KingdomTransfers boundary. Transfer Evidence destination Actions accept scalar Evidence/review/scope identifiers plus typed owner values and return scalar receipts.

The capability is not placed in `app/Workflows`; Kingdom Transfer is a GameWorld business capability with its own state and invariants.

## Terminology boundary

**Transfer Cohort** is an Alliance-owned coordination bucket inside a Transfer Plan. **Transfer Group** is only the official KingShot event grouping of Kingdoms. See [ADR 0011](../../adr/0011-separate-transfer-planning-cohorts-from-official-transfer-groups.md).

Official Transfer Group membership is scoped to one Transfer Window. No timeless `Kingdom.transfer_group` attribute exists.

## Decision boundary

Eligibility is derived, not persisted. `TransferEligibilityEvaluator` accepts typed, already-scoped inputs and returns a structured assessment containing per-requirement states and next actions. Controllers, jobs, Vue components and generic read models do not reproduce game eligibility rules.

Readiness remains an independent planning concern. A participant may be workflow-ready while game eligibility is blocked or unverified, and may be game-eligible while Alliance planning remains incomplete.

## Evidence and freshness boundary

Material eligibility facts carry source/reference and observation time. Mutable Governor observations also carry an explicit validity boundary. Missing, stale, conflicting or non-authoritative information cannot produce `eligible_now`.

KingdomTransfers owns freshness. Evidence may supply reviewer-confirmed `observed_at` and the explicit validity boundary required by this product contract, but Evidence does not invent a global TTL or determine whether an owner fact is current.

The initial Transfer Evidence schemas cannot set `in_game_rules_verified`. A fresh owner observation for that requirement remains independently necessary before eligibility can become `eligible_now`. Required Transfer Passes are observed; there is no Transfer Score → required-pass calculation.

Community projects and guides are discovery evidence only. Unpublished or unverified game rules remain evidence-gated instead of being inferred into product truth.

## Transfer Evidence destination boundary

The second Screenshot Intake family enters KingdomTransfers only through five dedicated owner Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Each Action:

1. reacquires and authorizes current Alliance Transfer authority;
2. checks for an existing destination receipt under the immutable approved-review idempotency key;
3. for a new write, locks and re-resolves the current Plan/participant/Transfer Window/target scope against the reviewed snapshot;
4. validates the Evidence reference through the Evidence owner contract;
5. delegates owner invariant/persistence logic to the shared internal writer;
6. appends owner history and audit/outbox evidence;
7. persists and returns a scalar destination receipt.

Material destination-scope drift rejects the new write. The Action never silently retargets a reviewed screenshot.

## Shared owner-internal writers

Normal owner Actions and Transfer Evidence destination Actions share the same internal writers after their respective authorization boundaries:

- `TransferObservationWriter` owns typed observation validation, target requirements, validity checks, deterministic observation identity, append-only observation persistence and observation audit/outbox;
- `TransferKingdomConditionWriter` owns target resolution, Power Cap/classification validation, Phase-II correction invariants, append-only condition history and audit/outbox;
- `TransferGroupWriter` owns complete official-group membership validation, same-window membership conflicts, revision/supersession semantics and audit/outbox.

Public owner Actions such as `RecordTransferObservation`, `RecordTransferKingdomCondition` and `SaveTransferGroup` authorize and then delegate to these writers. Transfer Evidence Actions authorize once through `TransferEvidenceDestinationSupport` and then invoke the same writers directly. This prevents duplicate authorization logic while keeping every external owner mutation behind an authorized Action.

`RecordTransferScorePassEvidence` invokes `TransferObservationWriter` three times inside one outer database transaction. Transfer Score, passes available, passes required and the destination receipt therefore commit atomically.

## Destination idempotency and recovery

Semantic duplicate detection remains an Evidence review concern. Destination idempotency is separate and is enforced by `transfer_evidence_receipts.idempotency_key`.

The destination Action checks an existing receipt before requiring the historical Plan/participant to remain mutable. This supports the crash window where KingdomTransfers committed successfully but Evidence failed before acknowledging the receipt: an authorized retry using the same immutable review key returns the existing receipt without appending another observation, condition or group revision.

A genuinely newer approved Evidence revision uses new reviewed meaning/observation time and a new destination idempotency key, so it may append new owner history.
