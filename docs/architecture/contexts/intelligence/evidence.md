# Intelligence / Evidence

Status: Current — Architecture V3

`Intelligence/Evidence` owns game evidence intake and the provenance of attempts to understand that evidence. It deliberately does not own the domain facts that a reviewed screenshot may eventually create.

Screenshot Intake currently has two explicitly supported evidence families:

1. Bear Hunt battle reports, committed to `Operations/Results`;
2. Transfer participant evidence, committed to `GameWorld/KingdomTransfers` through five versioned screenshot schemas.

This is one Evidence capability. There is no Transfer OCR bounded context, generic `transfer_ocr` schema, or unconstrained polymorphic evidence-target abstraction.

## Ownership

Evidence owns:

- private uploaded screenshot objects and immutable source metadata;
- source/derived-representation checksums;
- security-scan results and private-storage lifecycle;
- OCR/provider, classification and extraction attempts with implementation/provider/schema versions;
- extracted field candidates, raw text, normalized values, confidence, bounding regions and warnings;
- immutable review revisions and manual corrections/exclusions;
- Player-resolution decisions represented as scalar foreign IDs where a schema requires them;
- exact, visual and semantic duplicate evidence decisions;
- commit attempts, destination idempotency keys and destination receipts;
- retry/recovery state;
- evidence deletion, redaction and retention lifecycle;
- the minimum scalar source/destination references required to authorize and explain a cross-context handoff.

Evidence does not own:

- Player or Alliance identity/membership;
- Event or EventOccurrence state;
- Bear Hunt battle-report results or aggregate damage;
- Transfer Plans, participants, Transfer Windows or phase semantics;
- official Transfer Groups or their window-scoped Kingdom membership;
- target-Kingdom Transfer conditions;
- Governor Transfer observations;
- observation validity/freshness, conflict resolution or eligibility calculations;
- any other domain state inferred from evidence.

## Boundary

A screenshot is evidence, not domain truth. Machine classification/extraction output remains candidate state until an authorized review approves a concrete revision. Cross-context commit is coordinated by an `Intelligence/Evidence` application Action. It builds reviewed scalar meaning from Evidence-owned state, invokes the destination owner's public Action, then records only the returned destination receipt in Evidence. The destination revalidates current authority and domain invariants in its own transaction.

This capability does not add a new top-level `app/Workflows` family. Architecture V3 keeps that workflow set closed; Screenshot Intake's commit handshake is capability-local orchestration over owner Actions and scalar/value-object contracts.

For Bear Hunt, `Operations/Results` owns accepted battle-report ledgers, report entries, and the recomputed `EventPlayerResult` aggregates. `Intelligence/Evidence` records only the destination receipt and provenance link.

For Transfer Evidence, `GameWorld/KingdomTransfers` owns accepted observations, target conditions, official groups, freshness and derived eligibility. Evidence may retain scalar `transfer_plan_id` and `transfer_participant_id`; approved review revisions additionally snapshot scalar `transfer_window_id`, direction/target meaning where material, schema kind/version and reviewed values so destination-scope drift can be detected before commit.

No foreign Eloquent model crosses the boundary. A retained `player_id`, `alliance_id`, `occurrence_id`, `transfer_plan_id`, `transfer_participant_id`, `transfer_window_id`, target Kingdom ID or destination receipt ID is a scalar reference and does not transfer ownership.

## Narrow evidence scopes

Evidence persistence supports only the concrete scopes required by its two product families:

- **Bear Hunt:** `occurrence_id` present, Transfer Plan/participant references absent.
- **Transfer participant:** `occurrence_id` absent, both `transfer_plan_id` and `transfer_participant_id` present.

The application validates those combinations and the database enforces the same exclusive shape. This is intentionally not a generic `target_type`/`target_id` framework.

Transfer scope is re-resolved at every protected upload/review/duplicate/commit/retry/delete mutation. Destination commit additionally locks current owner state and verifies the approved review's Plan, participant, Transfer Window and target-Kingdom snapshot. A material change requires a new review; evidence is never silently retargeted.

## Explicit Transfer schema boundary

The five v1 Transfer screenshot classes are separate Evidence kinds:

- `transfer_governor_status`;
- `transfer_score_passes`;
- `transfer_invitation`;
- `transfer_target_kingdom_rules`;
- `transfer_official_group`.

Each is registered with a schema version, supported/required fields, normalizer, classifier threshold, field-confidence threshold, fixture corpus, semantic-fingerprint contract and owner destination Action. Extractors can return only fields explicitly supported by their registered schema. Review rejects fields outside that schema contract.

The expected screenshot class selected by the user is a hint only. Classification independently determines the supported kind; a mismatch is not routed to the selected extractor.

None of the v1 Transfer schemas supports `in_game_rules_verified`. No Transfer Evidence extractor, review input or preview input may manufacture that fact, and required Transfer Passes are observed rather than calculated from Transfer Score.

## Review, freshness and derived state

All current Transfer Evidence requires human review. Human correction appends reviewed meaning and does not rewrite machine output or confidence.

Evidence distinguishes upload time, source metadata time when trustworthy, visible in-game timestamps when fixture-proven, reviewer-confirmed `observed_at`, and owner validity boundaries. Evidence has no hidden global freshness TTL. Mutable Transfer observations require an explicit validity boundary before approval where the KingdomTransfers product contract uses them as current eligibility evidence.

`GameWorld/KingdomTransfers` remains the sole evaluator of stale, conflicting, non-authoritative and missing facts. The Evidence preview calls the same owner eligibility evaluator against an in-memory substitution of reviewed facts and persists no hypothetical eligibility flag. It does not alter the current `in_game_rules_verified` requirement.

## Duplicate semantics and destination idempotency

Duplicate controls solve different problems and remain separate:

- **Exact duplicate:** source binary identity, checked only inside the authorized evidence scope.
- **Visual duplicate:** advisory perceptual similarity; the screenshot remains reviewable.
- **Semantic duplicate:** deterministic reviewed meaning for a schema and Transfer scope; equivalent game state is blocked until explicitly resolved.
- **Destination idempotency:** one stable key for one immutable approved review, enforced by the owner receipt so retries cannot append duplicate domain history.

A genuinely newer Transfer observation has different reviewed meaning and/or observation boundary and remains importable as a new historical owner observation.

## Destination Actions and atomicity

Transfer Evidence commits through dedicated `GameWorld/KingdomTransfers` owner Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

They share owner-internal destination support and reuse the existing observation, target-condition and official-group writers rather than duplicating authorization or invariants. Each reacquires current Alliance authority, locks/re-resolves Plan/participant/window/target scope, validates Evidence provenance through the Evidence lookup contract, enforces typed values and appends owner history.

`RecordTransferScorePassEvidence` records Transfer Score, passes available and passes required inside one outer owner transaction. All three observations and the destination receipt commit together or none do.

Target-Kingdom rules continue through the existing phase/correction invariant; a screenshot cannot bypass the post-Phase-II correction rules. Official-group evidence continues through the owner group/membership conflict and revision semantics.

## Immutability

The original uploaded object is never rewritten. Preprocessing creates a derived representation with its own checksum and parent evidence identity. Classification/extraction retries append new attempts rather than updating historical output. Human corrections append review revisions and do not rewrite the machine confidence that produced the candidate.

## Idempotency and recovery

A commit attempt has a stable destination idempotency key for one immutable approved review revision. The destination owner stores a scalar receipt under that key and returns the existing receipt for an authorized replay.

This deliberately covers the cross-context crash window:

1. Evidence begins/resumes a commit attempt;
2. the owner transaction succeeds and stores the receipt;
3. Evidence acknowledgement fails or the process exits;
4. a later normal retry uses the same destination key;
5. the owner returns the existing receipt without appending domain state;
6. Evidence records the recovered acknowledgement.

A failed Evidence acknowledgement remains historical attempt state; operators do not repair it by editing owner or Evidence tables.

## Deletion and retention

Deleting Evidence does not cascade into committed domain results. Once a destination owner accepts reviewed meaning, correction/removal is an explicit audited owner action there. Evidence retention may remove binary/image payloads and sensitive OCR/raw text while retaining the minimum committed provenance/tombstone, review history, commit history and destination receipt needed to explain the historical handoff.

## Shared infrastructure

Upload security is a technical concern under `Shared/Infrastructure/Uploads`. Alliance Content and Intelligence Evidence consume the same scanner contract; Intelligence does not depend on Alliance Content merely to inspect a file.