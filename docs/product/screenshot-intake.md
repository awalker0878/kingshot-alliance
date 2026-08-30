# Screenshot Intake

Status: Current complete product contract — all three Screenshot Intake families complete (2026-08-30)

Screenshot Intake turns user-provided KingShot screenshots into reviewed domain commands without transferring ownership of the resulting game data to the intake pipeline. It is one `Intelligence/Evidence` capability with explicit, versioned evidence families rather than a generic OCR/domain-ingestion framework.

The supported families are:

1. **Bear Hunt battle report** — Event-occurrence-scoped Evidence committed to `Operations/Results`.
2. **Transfer Evidence** — Transfer-Plan/participant-scoped Evidence committed to `GameWorld/KingdomTransfers` through five explicit screenshot schemas.
3. **Governor Progression Evidence** — Alliance-roster-entry-scoped Evidence normalized against an immutable `GameWorld/Progression` dataset and committed to `Intelligence/Roster` through six explicit screenshot schemas.

The complete extension contracts are:

- [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md);
- [Screenshot Intake: Governor Progression](./screenshot-intake-governor-progression.md).

Those extension documents are the implementation sources of truth for their schema-specific fields, normalization, confidence thresholds, fixture corpora, review rules, duplicate semantics, destination Actions, preview behavior and delivery ledgers.

There is no Transfer OCR or Governor OCR bounded context, generic OCR schema, unconstrained bag-of-fields destination model or generic polymorphic evidence-target framework.

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
- normalization attempts where a family requires them, including pinned factual-dataset provenance;
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
- Transfer observations, freshness/validity/conflict semantics or eligibility decisions;
- canonical Hero/Gear/Charm/Progression identities or factual progression tables;
- accepted Governor progression observations/current-state projection.

Destination ownership remains explicit:

- `Operations/Results` owns accepted Bear Hunt battle reports, report entries and derived Event result state.
- `GameWorld/KingdomTransfers` owns accepted Transfer observations, target conditions, official Transfer Groups, freshness/validity/conflict semantics and eligibility.
- `Intelligence/Roster` owns accepted append-only Governor progression observations and their current-state projection.
- `GameWorld/Progression` remains immutable factual catalogue truth and is a read-only dependency of Governor Progression normalization/validation.

Cross-context writes use scalar IDs/value objects through destination-owner Actions. No foreign Eloquent model crosses the boundary. Shared Evidence reference contracts remain family-neutral; a family-specific destination provenance check uses an explicit Evidence-owned family contract rather than adding family-specific methods to a shared lookup used by other screenshot families.

## Narrow Evidence scopes

Persistence supports only the explicit product scopes now required:

- **Bear Hunt:** `occurrence_id` present; Transfer and Governor/Roster references absent.
- **Transfer participant:** `occurrence_id` absent; `transfer_plan_id` and `transfer_participant_id` present together; Governor/Roster reference absent.
- **Governor Progression:** `occurrence_id`, Transfer Plan and Transfer participant references absent; `roster_entry_id` present with the owning Alliance scope.

Application validation and database constraints enforce those combinations. Adding a family does not justify a generic `target_type` / `target_id` abstraction.

## Shared lifecycle

Persisted Evidence lifecycle states remain explicit:

`uploaded → classifying → classified → extracting → needs_review → approved → committing → committed`

Families may insert an explicit machine-owned normalization step between extraction and review where their contract requires it. Governor Progression does so in order to pin canonical interpretation to one immutable Progression dataset release.

Exceptional states are `unsupported`, `failed` and `deleted`. Machine retries append immutable attempts rather than overwriting historical output. Human corrections append reviewed meaning and never rewrite machine output or confidence.

All currently supported screenshot classes require human review. Automatic commit is outside the current product contract.

## Classification and extraction contract

The user-selected expected screenshot class is a hint, not truth. Classification independently verifies the actual supported class. A mismatch is surfaced as unsupported/needs user correction and must never be routed blindly through the selected extractor.

A fixture-proven explicit class header may independently satisfy a schema's classification threshold for a supported safe crop when the fixture corpus proves that header is unambiguous. Classification behavior is versioned provenance: changing scoring/threshold behavior requires a classifier-version change so historical attempts remain explainable.

Every extractor is schema-bound and may emit only fields proven by that schema's executable fixture corpus. A field not fixture-proven for that schema cannot be extracted, reviewed into a commit command or committed by the destination.

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

Their complete independent contract lives in [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md). Required Transfer Passes remain observed facts, generic Transfer screenshots never prove `in_game_rules_verified=true`, official-group membership is schema-bound, and freshness/eligibility remain KingdomTransfers-owned.

### Governor Progression family

The six explicit v1 classes are:

- `governor_profile`;
- `governor_hero_roster`;
- `governor_hero_detail`;
- `governor_hero_gear`;
- `governor_gear`;
- `governor_charms`.

Pets and Masters are not implied by these schemas and remain unsupported until later explicit fixture-backed schema releases.

Governor Progression normalization uses `GameWorld/Progression` only as immutable factual reference. Each normalization attempt pins the dataset ID/checksum and normalizer version. Canonical identities are candidates until human review accepts the concrete reviewed meaning. A new Progression release never silently reinterprets an old machine attempt or accepted Roster observation.

Missing means unknown/not observed. Only the Hero Roster schema may assert complete roster capture, and only when the screenshot/reviewer establishes that completeness. A partial Hero, Gear or Charm screenshot never erases unshown facts.

## Review and confidence

Confidence is retained per extracted field together with raw observation and normalized candidate. The review surface makes machine output understandable before approval and identifies manual corrections without changing historical machine confidence.

Transfer review additionally exposes owner freshness/eligibility semantics. Governor Progression review additionally exposes:

- target Governor/roster entry;
- expected versus detected class and classification confidence;
- schema version and executable fixture corpus;
- raw OCR versus normalized fields, confidence, bounding regions and warnings;
- pinned Progression dataset ID/version/checksum;
- canonical identity candidates and identity confidence;
- reviewer-confirmed `captured_at`;
- complete-roster meaning where applicable;
- exact/visual/semantic duplicate state;
- before/after Roster projection preview;
- scalar destination receipt after commit.

## Duplicate contract

Duplicate controls solve different problems:

1. **Exact duplicate** — binary identity inside the authorized Evidence scope only. It must never disclose cross-Alliance evidence.
2. **Visual duplicate** — perceptual similarity warning. Distinct evidence remains reviewable.
3. **Semantic duplicate** — deterministic fingerprint over stable reviewed meaning and correct owner scope. Equivalent reviewed game state is blocked until an explicit supported resolution.
4. **Destination idempotency** — stable key for one immutable approved review; destination-owner persistence returns the same receipt on retry instead of appending duplicate domain state.

A genuinely newer observation remains importable and appends destination-owner history.

## Destination contracts

### Bear Hunt

`Operations/Results` receives scalar reviewed Bear Hunt meaning and reacquires authority, validates occurrence/player scope, enforces idempotency/database uniqueness, appends the accepted report ledger, recomputes owned aggregates deterministically and returns a scalar receipt. Evidence cannot directly write Operations result models.

### Transfer Evidence

Transfer Evidence commits through five dedicated `GameWorld/KingdomTransfers` Actions:

- `RecordGovernorStatusEvidence`;
- `RecordTransferScorePassEvidence`;
- `RecordTransferInvitationEvidence`;
- `RecordTransferKingdomRulesEvidence`;
- `RecordOfficialTransferGroupEvidence`.

Those Actions reacquire current Alliance authority, verify approved review provenance/scope, enforce typed owner invariants and return scalar receipts.

### Governor Progression Evidence

Governor Progression commits through six dedicated `Intelligence/Roster` Actions:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

Those Actions reacquire current Roster authority, validate the exact approved review provenance and dataset pin through the Governor-specific Evidence provenance contract, validate a closed typed payload against `GameWorld/Progression`, append immutable Roster-owned history, enforce destination idempotency and return scalar receipts. Evidence never writes Roster tables directly.

## Cross-context commit recovery

Evidence coordinates a commit handshake; it does not own destination persistence. The destination idempotency key is stable for the immutable approved review meaning.

If the destination commits and the caller exits before Evidence records acknowledgement, retry uses the same destination key. The destination returns the existing authorized receipt without appending duplicate owner history, after which Evidence records the recovered acknowledgement. Failed Evidence acknowledgement/attempt history is not repaired by editing owner tables.

## Freshness, observation time and derived truth

Evidence distinguishes upload time, trustworthy source metadata time, fixture-proven visible in-game timestamps, reviewer-confirmed observation time and owner-specific validity/current-state semantics.

Evidence does not own a global freshness TTL. KingdomTransfers owns Transfer freshness/eligibility. Intelligence/Roster owns the append-only Governor observation history and current-state projection; the projection resolves latest observed values per fact and preserves provenance rather than treating the newest partial screenshot as a full replacement.

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
- Governor Progression Evidence starts from the authorized Governor Progression/Roster workflow via **Update from screenshot**.

Supported UX provides upload/progress/failure/retry, accessible retained-image access, expected-versus-detected class, raw/normalized/confidence presentation, human correction, duplicate messaging, before/after owner preview, explicit commit, destination receipt, responsive layout, keyboard operation, non-colour-only confidence/error semantics, accessible destructive confirmation and localized player-facing labels.

## Observability

Audit/outbox/diagnostics cover material lifecycle transitions: upload accepted/rejected, classify/extract/normalize attempted/failed, review approved, duplicate blocked/resolved, commit started/succeeded/failed/recovered, destination deduplication, Evidence deletion/redaction/purge and retention failures. Diagnostics remain privacy-safe.

## Family delivery status

A family is `Complete` only when its behavior, authorization, persistence, UX, accessibility, localization, observability, fixture contract, tests, documentation and all applicable repository gates pass on one immutable release candidate.

| Family | Status | Source of truth |
| --- | --- | --- |
| Bear Hunt battle report | Complete | This document plus Operations/Results architecture/reference/runbooks |
| Transfer Evidence | Complete | [Screenshot Intake: Transfer Evidence](./screenshot-intake-transfer-evidence.md) |
| Governor Progression Evidence | Complete | [Screenshot Intake: Governor Progression](./screenshot-intake-governor-progression.md) |

The umbrella Screenshot Intake capability is complete. A future family-level regression or failed required gate reopens the affected family and umbrella row.

## Cross-family invariants

1. Evidence ownership never expands into ownership of the extracted domain fact.
2. Public owner write contracts use scalar IDs/value objects and never accept/return foreign Eloquent models.
3. Machine attempts and human review remain immutable/auditable until their retention boundary.
4. No current supported screenshot is auto-committed.
5. A schema may extract/commit only fixture-proven fields.
6. Classification verifies the expected class rather than trusting it.
7. Exact/visual/semantic duplicate semantics remain distinct from destination idempotency.
8. Evidence deletion never silently rewrites accepted owner history.
9. Extractor/provider/normalizer/classifier concerns remain behind versioned provenance contracts.
10. Vue/controllers do not own extraction rules, normalization formulas, retention policy or destination-domain validation.
11. No compatibility shim, generic OCR family or unconstrained target polymorphism is introduced.
12. Governor Progression Evidence cannot create, modify, correct or infer `GameWorld/Progression` truth.
13. Shared Evidence reference contracts cannot acquire family-specific provenance methods; family-specific validation uses an explicit family-owned contract so one screenshot family cannot break another family's boundary.

## Definition of done

Each extension may be marked complete only when its own delivery ledger and acceptance criteria are complete and one immutable candidate passes all applicable clean PostgreSQL, PHP behavior, Pint, PHPStan, frontend lint/format/type/build, architecture, accessibility/visual regression, CodeQL, dependency/security and repository-wide release gates.

Governor Progression additionally requires all six executable fixture corpora, dataset-pinned normalization, scope-drift protection, human review, tenant-safe duplicate handling, six Roster destination Actions, destination recovery/idempotency, partial/complete observation semantics, current-state provenance projection, owning-workflow UX and product/architecture/reference/operations reconciliation.
