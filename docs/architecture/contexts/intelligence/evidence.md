# Intelligence / Evidence

Status: Current — Architecture V3

`Intelligence/Evidence` owns game evidence intake and the provenance of attempts to understand that evidence. It deliberately does not own the domain facts that a reviewed screenshot may eventually create.

Screenshot Intake has three explicitly supported evidence families:

1. Bear Hunt battle reports, committed to `Operations/Results`;
2. Transfer participant evidence, committed to `GameWorld/KingdomTransfers` through five versioned screenshot schemas;
3. Governor Progression evidence, normalized against immutable `GameWorld/Progression` catalogue releases and committed to `Intelligence/Roster` through six versioned screenshot schemas.

This is one Evidence capability. There is no Transfer OCR or Governor OCR bounded context, generic OCR schema, or unconstrained polymorphic evidence-target abstraction.

## Ownership

Evidence owns:

- private uploaded screenshot objects and immutable source metadata;
- source/derived-representation checksums;
- security-scan results and private-storage lifecycle;
- OCR/provider, classification and extraction attempts with implementation/provider/schema versions;
- Governor Progression normalization attempts including pinned Progression dataset ID/checksum, normalizer version, canonical candidate, identity confidence and warnings;
- extracted field candidates, raw text, normalized values, confidence, bounding regions and warnings;
- immutable review revisions and manual corrections/exclusions;
- Player/canonical-identity resolution decisions represented as scalar IDs where a schema requires them;
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
- Governor Transfer observations or Transfer freshness/eligibility;
- canonical `GameWorld/Progression` entities/facts;
- accepted Governor progression observation history or current-state projection;
- any other domain state inferred from evidence.

## Boundary

A screenshot is evidence, not domain truth. Machine classification/extraction/normalization output remains candidate state until an authorized review approves a concrete revision. Cross-context commit is coordinated by an `Intelligence/Evidence` application Action. It builds reviewed scalar meaning from Evidence-owned state, invokes the destination owner's public Action, then records only the returned destination receipt in Evidence. The destination revalidates current authority and domain invariants in its own transaction.

This capability does not add a new top-level `app/Workflows` family. Architecture V3 keeps that workflow set closed; Screenshot Intake's commit handshake is capability-local orchestration over owner Actions and scalar/value-object contracts.

For Bear Hunt, `Operations/Results` owns accepted battle-report ledgers, report entries and recomputed result aggregates.

For Transfer Evidence, `GameWorld/KingdomTransfers` owns accepted observations, target conditions, official groups, freshness and derived eligibility. Evidence may retain the narrow scalar Plan/participant/window snapshot required to detect destination-scope drift.

For Governor Progression, `Intelligence/Roster` owns accepted append-only progression observations and current-state projection. `GameWorld/Progression` is a read-only factual catalogue dependency used for normalization and owner validation; no Evidence path can mutate canonical Progression truth.

No foreign Eloquent model crosses the boundary. Retained identifiers are scalar references and do not transfer ownership.

## Narrow evidence scopes

Evidence persistence supports only the concrete scopes required by its product families:

- **Bear Hunt:** `occurrence_id` present; Transfer and Governor/Roster references absent.
- **Transfer participant:** `occurrence_id` absent; both `transfer_plan_id` and `transfer_participant_id` present; Governor/Roster reference absent.
- **Governor Progression:** occurrence and Transfer references absent; `roster_entry_id` present with the owning Alliance scope.

The application validates those combinations and the database enforces the same exclusive shape. This is intentionally not a generic `target_type`/`target_id` framework.

Protected scope is re-resolved at upload/review/duplicate/commit/retry/delete mutations. Destination commit additionally locks/re-resolves current owner scope and verifies the approved review snapshot. Material scope change requires new review; Evidence is never silently retargeted.

## Explicit Transfer schema boundary

The five v1 Transfer screenshot classes are separate Evidence kinds:

- `transfer_governor_status`;
- `transfer_score_passes`;
- `transfer_invitation`;
- `transfer_target_kingdom_rules`;
- `transfer_official_group`.

Each is registered with a schema version, supported/required fields, classifier/field confidence thresholds, executable fixture corpus, semantic-fingerprint contract and owner destination Action. Required Transfer Passes are observed rather than calculated and no v1 Transfer schema may manufacture `in_game_rules_verified`.

## Explicit Governor Progression schema boundary

The six v1 Governor Progression screenshot classes are separate Evidence kinds:

- `governor_profile`;
- `governor_hero_roster`;
- `governor_hero_detail`;
- `governor_hero_gear`;
- `governor_gear`;
- `governor_charms`.

Each is registered with its own schema version, supported/required fields, confidence thresholds, executable fixture corpus, normalization contract and dedicated Roster destination Action. Pets and Masters are not accepted by implication.

The expected screenshot class selected by the user is a hint only. Classification independently determines the supported kind; a mismatch is not routed blindly to the selected extractor. Extractors may emit only schema-allowlisted fields proven by their executable fixtures.

## Governor Progression normalization

`GameWorld/Progression` remains immutable catalogue truth. Governor Progression normalization reads one release and stores its dataset ID/checksum with the immutable normalization attempt. Canonical Hero matching, factual bounds and aliases are interpreted only through that pinned release.

A new catalogue release does not rewrite a prior normalization. Explicit re-normalization creates another attempt against a newly pinned release and requires review. Human correction creates reviewed meaning without rewriting raw OCR, extracted candidates or machine confidence.

Missing screenshot content means unknown/not observed. A partial Hero/Gear/Charm capture cannot erase state not shown. Only the Hero Roster schema may carry explicit complete-roster meaning when fixture/reviewer semantics establish it.

See `docs/architecture/contexts/intelligence/governor-progression-evidence.md` for the focused boundary.

## Review, freshness and derived state

All current Screenshot Intake families require human review. Evidence distinguishes upload time, trustworthy source metadata time, visible in-game time where fixture-proven and reviewer-confirmed observation/capture time.

`GameWorld/KingdomTransfers` remains the sole evaluator of Transfer stale/conflicting/non-authoritative/missing facts and derived eligibility. `Intelligence/Roster` remains the owner of Governor observation history/current-state composition. Evidence preview invokes owner semantics against hypothetical reviewed meaning and persists no owner-derived current-state flag.

## Duplicate semantics and destination idempotency

Duplicate controls remain separate:

- **Exact duplicate:** source binary identity, checked only inside the authorized Evidence scope.
- **Visual duplicate:** advisory perceptual similarity; the screenshot remains reviewable.
- **Semantic duplicate:** deterministic reviewed meaning for the concrete schema/owner scope; equivalent state is blocked until explicitly resolved.
- **Destination idempotency:** one stable key for one immutable approved review, enforced by the destination receipt so retries cannot append duplicate domain history.

A genuinely newer observation remains importable.

## Destination Actions

Transfer Evidence commits through dedicated `GameWorld/KingdomTransfers` owner Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Governor Progression commits through dedicated `Intelligence/Roster` owner Actions:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

Each destination reacquires current authority, verifies exact approved Evidence provenance, enforces typed owner invariants, appends owner history and returns a scalar receipt. Governor Progression Actions delegate to the Roster observation writer and validate their pinned Progression release/checksum before acceptance.

## Immutability

The original uploaded object is never rewritten. Preprocessing creates a derived representation with its own checksum and parent evidence identity. Classification/extraction/normalization retries append attempts rather than updating historical output. Human corrections append review revisions and do not rewrite machine confidence.

## Idempotency and recovery

A commit attempt has a stable destination idempotency key for one immutable approved review revision. The destination owner stores a scalar receipt under that key and returns the existing receipt for an authorized replay.

This covers the cross-context crash window:

1. Evidence begins/resumes a commit attempt;
2. the owner transaction succeeds and stores the receipt;
3. Evidence acknowledgement fails or the process exits;
4. a later retry uses the same destination key;
5. the owner returns the existing receipt without appending domain state;
6. Evidence records the recovered acknowledgement.

A failed Evidence acknowledgement remains historical attempt state; operators do not repair it by editing owner or Evidence tables.

## Deletion and retention

Deleting Evidence does not cascade into committed domain results or observations. Once a destination owner accepts reviewed meaning, correction/removal is an explicit audited owner action there. Evidence retention may remove binary/image payloads and sensitive OCR/raw text while retaining the minimum committed provenance/tombstone, review history, commit history and destination receipt needed to explain the handoff.

## Shared infrastructure

Upload security is a technical concern under `Shared/Infrastructure/Uploads`. Alliance Content and Intelligence Evidence consume the same scanner contract; Intelligence does not depend on Alliance Content merely to inspect a file.
