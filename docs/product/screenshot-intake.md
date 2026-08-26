# Screenshot Intake

Status: Current product contract — Bear Hunt family complete; Transfer Evidence family in release verification (2026-08-26)

Screenshot Intake turns user-provided KingShot screenshots into reviewed domain commands without transferring ownership of the resulting game data to the intake pipeline. It is one `Intelligence/Evidence` capability with explicit, versioned evidence families rather than a generic OCR/domain-ingestion framework.

The supported families are:

1. **Bear Hunt battle report** — Event-occurrence-scoped Evidence committed to `Operations/Results`.
2. **Transfer Evidence** — Transfer-Plan/participant-scoped Evidence committed to `GameWorld/KingdomTransfers` through five explicit screenshot schemas.

The complete Transfer Evidence contract is [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md). That extension document is the implementation source of truth for its schema-specific fields, normalization, confidence thresholds, fixture corpora, review rules, semantic fingerprints, destination Actions, freshness behavior, preview behavior and delivery ledger.

There is no Transfer OCR bounded context, generic `transfer_ocr` schema, unconstrained bag-of-fields extraction model or generic polymorphic evidence-target framework.

## Product outcome

An authorized Player can add a supported screenshot from the workflow that owns the destination decision, allow Evidence to security-scan/classify/extract only fixture-proven candidates, review and correct those candidates, understand duplicate/freshness/conflict implications, preview the exact owner-domain effect, and explicitly commit an immutable reviewed revision exactly once.

The original screenshot, machine attempts, normalized candidates, human corrections, duplicate decisions and destination receipt retain Evidence-owned provenance. Deleting/redacting Evidence never silently deletes or rewrites accepted owner-domain history.

## Ownership contract

`Intelligence/Evidence` owns:

- private screenshot binaries, source metadata, immutable checksums and derived-representation provenance;
- security-scan results;
- OCR/provider attempts;
- classification attempts and expected-versus-detected class decisions;
- extraction attempts, schema/extractor/provider versions and field candidates;
- raw observation, normalized candidate, field confidence, bounding regions and warnings;
- review revisions, manual corrections/exclusions and schema-scoped reviewed meaning;
- exact, visual and semantic duplicate Evidence decisions;
- commit attempts, retry/recovery state, stable destination-idempotency key and destination receipt;
- source redaction, deletion and retention lifecycle;
- only the narrow scalar scope references needed to authorize/explain a handoff.

Evidence does **not** own:

- Alliance membership or Player/Kingdom identity;
- Event/EventOccurrence lifecycle;
- Bear Hunt result ledgers or result aggregates;
- Transfer Plans, participants, Transfer Windows, official Transfer Groups or target-Kingdom conditions;
- Transfer observations, freshness/validity/conflict semantics or eligibility decisions.

Destination ownership remains explicit:

- `Operations/Results` owns accepted Bear Hunt battle reports, report entries and derived Event result state.
- `GameWorld/KingdomTransfers` owns accepted Transfer observations, target conditions, official Transfer Groups, freshness/validity/conflict semantics and eligibility.

Cross-context writes use scalar IDs/value objects through destination-owner Actions. No foreign Eloquent model crosses the boundary.

## Narrow Evidence scopes

Persistence supports only the explicit product scopes now required:

- **Bear Hunt:** `occurrence_id` present; Transfer Plan/participant references absent.
- **Transfer participant:** `occurrence_id` absent; `transfer_plan_id` and `transfer_participant_id` present together.

Application validation and database constraints enforce those combinations. Adding another family does not justify a generic `target_type` / `target_id` abstraction.

## Shared lifecycle

Persisted Evidence lifecycle states remain explicit:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Exceptional states are `unsupported`, `failed` and `deleted`. Machine retries append immutable attempts rather than overwriting historical output. Human corrections append reviewed meaning and never rewrite machine output or confidence.

All currently supported screenshot classes require human review. Automatic commit is outside the current product contract.

## Classification and extraction contract

The user-selected expected screenshot class is a hint, not truth. Classification independently verifies the actual supported class. A mismatch is surfaced as unsupported/needs user correction and must never be routed blindly through the selected extractor.

Every extractor is schema-bound and may emit only fields proven by that schema's fixture corpus. A field not fixture-proven for that schema cannot be extracted, reviewed into a commit command or committed by the destination.

### Bear Hunt family

The Bear Hunt extractor is intentionally narrow. Initial supported meaning includes visible report time where available, ranking rows, reported rank, Player display name and damage value. It does not infer troop composition, heroes, buffs, rally role, `rallies_joined`, `rallies_led` or other metrics that are not fixture-proven.

Player names extracted from screenshots are observations, not identity. Review resolves accepted rows to existing Players; Screenshot Intake cannot create or mutate Player identity.

### Transfer Evidence family

The five explicit v1 classes are:

- `transfer_governor_status`;
- `transfer_score_passes`;
- `transfer_invitation`;
- `transfer_target_kingdom_rules`;
- `transfer_official_group`.

Their complete independent contracts live in [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md). Important cross-family invariants are:

- required Transfer Passes are observed game facts and are never calculated from Transfer Score;
- generic Transfer screenshots never prove `in_game_rules_verified=true`;
- only the official-group schema may provide complete visible Transfer Group membership;
- only target-rules Evidence may provide target Power Cap/classification under its fixture-proven schema;
- mutable Governor/score/pass/invitation observations require reviewer-confirmed observation time and owner-defined validity; Evidence has no hidden global TTL.

## Review and confidence

Confidence is retained per extracted field together with raw observation and normalized candidate. The review surface must make machine output understandable before approval and must identify manual corrections without changing historical machine confidence.

For Transfer Evidence, the surface additionally shows:

- expected versus detected class and classification confidence;
- schema version and fixture corpus;
- raw versus normalized fields and field confidence/warnings;
- reviewer-confirmed `observed_at` and required validity where applicable;
- current owner-domain facts and eligibility state;
- visual/semantic duplicate warnings;
- before/after destination preview using the KingdomTransfers evaluator;
- the scalar destination receipt after commit.

## Duplicate contract

Duplicate controls solve different problems:

1. **Exact duplicate** — binary identity inside the authorized Evidence scope only. It must never disclose cross-Alliance evidence.
2. **Visual duplicate** — perceptual similarity warning. Distinct evidence remains reviewable.
3. **Semantic duplicate** — deterministic fingerprint over stable reviewed meaning and correct owner scope. Equivalent reviewed game state is blocked until an explicit supported resolution.
4. **Destination idempotency** — stable key for one immutable approved review; destination-owner persistence returns the same receipt on retry instead of appending duplicate domain state.

A genuinely newer observation remains importable and appends destination-owner history.

## Bear Hunt destination contract

`Operations/Results` receives scalar reviewed Bear Hunt meaning and must reacquire authority, validate occurrence/player scope, enforce idempotency/database uniqueness, append the accepted report ledger, preserve pre-import baselines, recompute owned aggregates deterministically and return a scalar receipt.

Evidence cannot directly write `EventPlayerResult` or any Operations model.

## Transfer destination contract

Transfer Evidence commits through five dedicated `GameWorld/KingdomTransfers` Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Those Actions reacquire current Alliance authority, verify the immutable approved review's Plan/participant/window/target scope, validate Evidence provenance, enforce typed values and owner invariants, and return scalar receipts. They delegate persistence/invariant logic to owner-internal `TransferObservationWriter`, `TransferKingdomConditionWriter` and `TransferGroupWriter`; normal KingdomTransfers writes use those same writers after their own authorization boundary.

Score/pass Evidence appends Transfer Score, passes available and passes required in one owner transaction so all three observations and the destination receipt commit together or none do.

## Cross-context commit recovery

Evidence coordinates a commit handshake; it does not own destination persistence. The destination idempotency key is stable for the immutable approved review meaning.

If the destination commits and the caller exits before Evidence records acknowledgement, retry uses the same destination key. The destination returns the existing authorized receipt without appending duplicate owner history, after which Evidence records the recovered acknowledgement. Failed Evidence acknowledgement/attempt history is not repaired by editing owner tables.

## Freshness and derived truth

Evidence distinguishes upload time, trustworthy source metadata time, fixture-proven visible in-game timestamps, reviewer-confirmed observation time and destination-owner validity.

Evidence does not own a global freshness TTL. `GameWorld/KingdomTransfers` remains solely responsible for stale/missing/conflicting/non-authoritative Transfer facts and for the `needs_verification`/requirement states they produce. Transfer Evidence preview calls the same owner evaluator against an in-memory substitution and never persists hypothetical eligibility or changes `in_game_rules_verified`.

## Deletion and retention

Evidence deletion/redaction and destination correction are separate capabilities.

- deleting Evidence never cascades into an accepted destination result/observation/condition/group;
- destination correction/removal is an explicit audited destination-owner operation;
- committed Evidence can lose binary/OCR/raw sensitive material according to retention while retaining the minimum review/commit/receipt provenance needed to explain the handoff;
- failed/unsupported/inactive uncommitted Evidence can be redacted/purged under Evidence retention policy;
- retention policy belongs to Evidence configuration/operations, not Vue/controllers or destination owners.

## Security and privacy

Uploads use private storage only. The pipeline validates allowlisted MIME/size/dimensions, verifies actual MIME, performs the shared upload scan, computes source identity, generates non-user-controlled storage names and removes staged bytes when persistence fails. Diagnostics must not leak screenshot content, raw hashes, Player/Alliance identity or cross-tenant duplicate information.

Authorization is checked before protected operations and reacquired at protected write/commit boundaries. Jobs/application Actions carry scalar IDs/value objects, never serialized Eloquent authority models.

## UX requirements

Screenshot Intake is embedded in the owning workflow rather than exposed as a generic AI/OCR page.

- Bear Hunt Evidence starts from the Bear Hunt occurrence/results workflow.
- Transfer Evidence starts from a Transfer participant via **Add in-game evidence**.

Supported UX must provide upload/progress/failure/retry, accessible retained-image access, expected-versus-detected class, raw/normalized/confidence presentation, human correction, duplicate messaging, before/after owner preview, explicit commit, destination receipt, responsive layout, keyboard operation, non-colour-only confidence/error semantics, accessible destructive confirmation and localized player-facing labels.

## Observability

Audit/outbox/diagnostics cover material lifecycle transitions: upload accepted/rejected, classify/extract attempted/failed, review approved, duplicate blocked/resolved, commit started/succeeded/failed/recovered, destination deduplication, Evidence deletion/redaction/purge and retention failures. Diagnostics remain privacy-safe.

## Family delivery status

A family is `Complete` only when its behavior, authorization, persistence, UX, accessibility, localization, observability, fixture contract, tests, documentation and all applicable repository gates pass on one immutable release candidate.

| Family | Status | Source of truth |
| --- | --- | --- |
| Bear Hunt battle report | Complete | This document plus Operations/Results architecture/reference/runbooks |
| Transfer Evidence | In release verification | [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md) |

The original Bear Hunt Screenshot Intake program remains complete. The umbrella Screenshot Intake capability is **not** declared fully closed while the Transfer Evidence delivery ledger or release verification has an incomplete/failed item.

## Cross-family invariants

1. Evidence ownership never expands into ownership of the extracted domain fact.
2. Public owner write contracts use scalar IDs/value objects and never accept/return foreign Eloquent models.
3. Machine attempts and human review remain immutable/auditable until their retention boundary.
4. No current supported screenshot is auto-committed.
5. A schema may extract/commit only fixture-proven fields.
6. Classification verifies the expected class rather than trusting it.
7. Exact/visual/semantic duplicate semantics remain distinct from destination idempotency.
8. Evidence deletion never silently rewrites accepted owner history.
9. Extractor/provider concerns remain behind versioned provenance contracts.
10. Vue/controllers do not own extraction rules, normalization formulas, retention policy or destination-domain validation.
11. No compatibility shim, generic Transfer OCR schema or unconstrained target polymorphism is introduced.

## Definition of done for the current two-family capability

Transfer Evidence may be marked complete only when:

- all five schema versions and fixture corpora execute as allowlist proofs;
- expected-class mismatch, low-confidence, missing-required-field and adjacent-number negatives fail safely;
- Transfer Score never implies required passes;
- no v1 Transfer Evidence path can set `in_game_rules_verified`;
- review corrections preserve machine provenance;
- scope drift after review forces rejection/re-review rather than silent retargeting;
- exact duplicates are tenant-safe, visual duplicates remain reviewable, semantic duplicates require explicit resolution and newer observations remain importable;
- all five dedicated KingdomTransfers destination Actions use shared owner-internal writers and return scalar receipts;
- score/pass commit is atomic;
- destination idempotency recovers the owner-success/Evidence-acknowledgement crash window without duplicate observations;
- owner freshness/conflict rules continue to produce `needs_verification` where evidence is missing/stale/conflicting/non-authoritative;
- deleting/redacting Evidence cannot cascade into committed KingdomTransfers history;
- participant UX is responsive, accessible, localized and visually regression-tested in review/preview/receipt states;
- product/architecture/reference/operations current-truth documents agree;
- clean PostgreSQL install, PHP tests, Pint, PHPStan, frontend lint/format/type/build, architecture/contracts, accessibility/visual regression, CodeQL, dependency review and all other applicable repository-wide release gates pass on one immutable candidate.

Until those conditions are verified on one candidate, the Transfer Evidence family and the umbrella Screenshot Intake extension remain open.
