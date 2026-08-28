# Intelligence / Evidence

Status: Current — Architecture V3

`Intelligence/Evidence` owns game-evidence intake and provenance of attempts to understand that evidence. It deliberately does not own the domain facts that a reviewed screenshot may eventually create.

Screenshot Intake has four explicit families:

1. Bear Hunt battle reports, committed to `Operations/Results`;
2. Transfer participant evidence, committed to `GameWorld/KingdomTransfers` through five versioned screenshot schemas;
3. Governor Progression evidence, normalized against immutable `GameWorld/Progression` releases and committed to `Intelligence/Roster` through six versioned screenshot schemas;
4. Territory map observations, pinned to immutable `GameWorld/KingdomMaps` releases and committed to `Intelligence/Observations` through the closed `territory-map-observation/1` schema.

There is no Transfer OCR, Governor OCR, Territory OCR or `TerritoryReality` bounded context, generic OCR schema, generic ingestion framework, unconstrained field-bag destination or polymorphic evidence-target abstraction.

## Ownership

Evidence owns private uploads/source metadata/checksums, upload scan results, OCR/provider/classification/extraction attempts, Governor Progression normalization attempts and dataset pins, extracted candidates/confidence/bounds/warnings, immutable review revisions/corrections, exact/visual/semantic duplicate decisions, commit attempts, destination idempotency keys/receipts, retry/recovery state, retention/deletion and only the minimum scalar scope references required to authorize/explain a handoff.

For Territory spatial Evidence, review provenance additionally records reviewer-confirmed capture time, coverage kind/completeness, explicit visible bounds when required, immutable KingdomMap ID/checksum and the reviewed closed object payload. These values are Evidence-owned reviewed meaning until destination acceptance; they are not yet the accepted observation fact.

Evidence does not own Player/Alliance identity, Event lifecycle/results, Transfer domain state/freshness/eligibility, canonical `GameWorld/Progression` or `GameWorld/KingdomMaps` truth, accepted Governor progression observation history/current-state projection, accepted Territory spatial observations, or desired Territory plan state.

## Cross-context boundary

A screenshot is Evidence, not domain truth. Machine classification/extraction/normalization remains candidate state until authorized human review approves a concrete revision. Evidence invokes destination-owner Actions using scalar IDs/value objects, then records only the scalar destination receipt. The destination revalidates current authority and invariants in its own transaction.

No foreign Eloquent model crosses the boundary.

Shared Evidence reference contracts remain family-neutral where possible. Governor Progression review provenance is exposed through the dedicated Evidence-owned `GovernorProgressionEvidenceReferenceLookup`; Territory spatial approved-review provenance is exposed through the dedicated `SpatialEvidenceReferenceLookup`. Other families do not acquire foreign domain/dataset-specific methods merely because the owner-side query implementation can answer multiple concerns.

For Bear Hunt, `Operations/Results` owns accepted report ledgers/aggregates. For Transfer, `GameWorld/KingdomTransfers` owns accepted observations, conditions, official groups, freshness and derived eligibility. For Governor Progression, `Intelligence/Roster` owns append-only observations/current state while `GameWorld/Progression` is a read-only factual-catalogue dependency. For Territory spatial screenshots, `Intelligence/Observations` owns accepted observed coordinates/coverage/provenance history while `GameWorld/KingdomMaps` supplies immutable map interpretation.

## Narrow Evidence scopes

Persistence supports only concrete family scopes:

- **Bear Hunt:** `occurrence_id` present; Transfer/Governor/Territory references absent.
- **Transfer participant:** `occurrence_id` absent; `transfer_plan_id` + `transfer_participant_id` present; Governor/Territory references absent.
- **Governor Progression:** occurrence/Transfer/Territory references absent; `roster_entry_id` present with owning Alliance scope.
- **Territory spatial:** occurrence/Transfer/Governor references absent; `kingdom_id`, `map_dataset_id` and `map_dataset_checksum` present with owning Alliance scope.

Application and database constraints enforce these mutually exclusive combinations. Protected scope is re-resolved at upload/review/duplicate/commit/retry/delete. Material scope change after review requires a new review.

## Explicit schema boundaries

Transfer v1 kinds are `transfer_governor_status`, `transfer_score_passes`, `transfer_invitation`, `transfer_target_kingdom_rules` and `transfer_official_group`.

Governor Progression v1 kinds are `governor_profile`, `governor_hero_roster`, `governor_hero_detail`, `governor_hero_gear`, `governor_gear` and `governor_charms`. Pets and Masters are not accepted by implication.

Territory spatial v1 has one closed kind, `territory_map_observation`, with schema version `territory-map-observation/1`. Its extractor may emit only fixture-proven supported coordinate candidates for HQ, Bear Trap, Banner and Governor City plus explicitly supported label/bounds/timestamp candidates. It may not infer Player identity, hidden/off-screen objects, plan correspondence, coverage completeness or missing state.

Each kind has a registered schema version, supported/required fields where applicable, confidence thresholds, executable fixture corpus and explicit destination Action. The user-selected class is a hint only; classification independently determines a supported kind or fails closed. Extractors emit only schema-allowlisted fixture-proven fields.

## Governor Progression normalization and retry

`GameWorld/Progression` remains immutable catalogue truth. Each Governor normalization attempt records dataset ID/checksum, normalizer version, normalized candidate, canonical identity candidate/confidence and warnings.

The **earliest normalization attempt establishes the automatic-processing dataset pin**, even when that attempt failed. After normalization history exists, automatic retry, queue redelivery or process restart must reuse that earliest ID/checksum and load the exact immutable release. It must never fall forward to `latest()`.

Only Evidence with no prior normalization attempt may select the current latest release for its first attempt. Moving an existing Evidence record to another dataset would be a separate explicit re-normalization product action; v1 does not provide that action.

Human correction creates new reviewed meaning without rewriting raw OCR, extraction candidates, normalization attempts or confidence.

## Governor catalogue validation versus structural observations

Destination validation uses the pinned release to the extent it exposes canonical meaning:

- Hero identities must resolve in the pinned Hero catalogue.
- Hero progression values follow closed bounds.
- Hero Gear enhancement/Mastery maxima derive from pinned published tables.
- Governor Gear tier/star values reconcile to pinned published steps when both are reviewed.
- Charm level is bounded by the pinned Governor Charm ladder.
- Gear/Charm `slot_id` values are closed screen-local structure, not invented Progression identities.
- OCR-visible Charm names remain Evidence provenance in v1; synthetic `charm_id` input is rejected.

Missing screenshot content means unknown/not observed. Partial Hero/Gear/Charm captures cannot erase state not shown. Only Hero Roster may carry explicit complete-roster meaning when fixture/reviewer semantics establish it.

See `docs/architecture/contexts/intelligence/governor-progression-evidence.md` for the focused boundary.

## Territory spatial review semantics

Every Territory spatial screenshot requires human review before commit. The reviewer explicitly confirms/corrects capture time, current Alliance/Kingdom scope, pinned map release, included objects/coordinates, Governor identity resolution, observation coverage kind, completeness and visible bounds when required.

Coverage/completeness are reviewed meaning, never OCR inference. `complete_hive` and `complete_visible_region` require explicit complete semantics; partial/single/unknown captures cannot establish global absence. Ambiguous/unresolved Governor identity stays ambiguous/unresolved through the Evidence handoff.

The exact pinned KingdomMap release is retained throughout upload/review/commit. Retry cannot silently reinterpret the Evidence against a different map release.

## Review and derived state

All current Screenshot Intake families require human review. Evidence distinguishes upload time, source metadata time, visible in-game time where fixture-proven and reviewer-confirmed capture time.

`GameWorld/KingdomTransfers` owns Transfer stale/conflicting/non-authoritative/missing semantics and derived eligibility. `Intelligence/Roster` owns Governor observation history/current-state composition. `Intelligence/Observations` owns accepted Territory spatial history. Evidence preview/review may invoke owner semantics against hypothetical reviewed meaning but persists no owner-derived current-state, drift, missing, freshness or coverage-delta flag.

## Duplicate semantics and destination idempotency

- **Exact duplicate:** source binary identity inside authorized Evidence scope.
- **Visual duplicate:** advisory perceptual similarity; distinct Evidence remains reviewable.
- **Semantic duplicate:** deterministic reviewed meaning in concrete owner scope; equivalent meaning requires supported resolution.
- **Destination idempotency:** one stable key per immutable approved review, enforced by destination receipt so retries cannot append duplicate domain history.

A genuinely newer observation remains importable.

For Territory spatial Evidence, semantic identity includes Alliance/Kingdom scope, schema/map pin, reviewer-confirmed capture/coverage boundary and reviewed object meaning. A visual duplicate is never destination idempotency.

## Destination Actions

Transfer commits through `RecordGovernorStatusEvidence`, `RecordTransferScorePassEvidence`, `RecordTransferInvitationEvidence`, `RecordTransferKingdomRulesEvidence` and `RecordOfficialTransferGroupEvidence`.

Governor Progression commits through `RecordGovernorProfileEvidence`, `RecordHeroRosterEvidence`, `RecordHeroDetailEvidence`, `RecordHeroGearEvidence`, `RecordGovernorGearEvidence` and `RecordGovernorCharmsEvidence`.

Territory spatial Evidence commits through `RecordSpatialObservationEvidence` owned by `Intelligence/Observations`.

Each destination reacquires current authority, verifies exact approved Evidence provenance, enforces typed owner invariants, appends owner history and returns a scalar receipt. Governor Actions additionally validate the exact pinned Progression release/checksum and catalogue-backed facts. Territory spatial commits additionally validate the exact pinned KingdomMap release/checksum, coverage/completeness combinations, coordinate bounds, closed object types and reviewed identity semantics.

No Territory Evidence destination invokes a TerritoryPlanning write. The accepted observation can later be compared with an immutable published plan revision by `ReadModels/TerritoryPlanning`, but Evidence itself never performs or persists that comparison.

## Immutability, recovery and retention

The original upload is never rewritten. Derived representations have independent provenance. Classification/extraction/normalization retries append attempts rather than mutating history. Human corrections append review revisions.

A commit attempt has one stable destination idempotency key. If the destination transaction succeeds but Evidence acknowledgement fails, replay of the same key returns the existing authorized receipt without duplicate owner history; Evidence then records recovered acknowledgement.

Deleting Evidence does not cascade into committed owner state. Owner correction/removal is a separate audited capability. For Territory spatial facts, correction appends a replacement observation and invalidates/supersedes the prior accepted observation; explicit invalidation records actor/time/reason. Evidence retention may remove binary/OCR/raw sensitive data while retaining minimum handoff provenance/tombstone/review/commit/receipt data.

## Shared infrastructure

Upload security is a technical concern under `Shared/Infrastructure/Uploads`. Alliance Content and Intelligence Evidence consume the same scanner contract; Intelligence does not depend on Alliance Content merely to inspect a file.
