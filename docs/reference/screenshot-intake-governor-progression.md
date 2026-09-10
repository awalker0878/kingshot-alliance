# Screenshot Intake: Governor Progression Reference

Status: Current structured intake contracts — pipeline verified 2026-09-08; retention hardening remains In progress under HARD-014–015.

## Purpose

Governor Progression Screenshot Intake converts supported KingShot screenshots into reviewed, append-only `Intelligence/Roster` observations. It never imports screenshot meaning into `GameWorld/Progression`; the Progression dataset is an immutable factual reference used only for normalization and validation.

## Supported screenshot classes

| Evidence kind | Schema | Destination |
| --- | --- | --- |
| `governor_profile` | `governor-profile/1` | `RecordGovernorProfileEvidence` |
| `governor_hero_roster` | `governor-hero-roster/1` | `RecordHeroRosterEvidence` |
| `governor_hero_detail` | `governor-hero-detail/1` | `RecordHeroDetailEvidence` |
| `governor_hero_gear` | `governor-hero-gear/1` | `RecordHeroGearEvidence` |
| `governor_gear` | `governor-gear/1` | `RecordGovernorGearEvidence` |
| `governor_charms` | `governor-charms/1` | `RecordGovernorCharmsEvidence` |
| `governor_buildings` | `governor-buildings/1` | `RecordStructuredProgressionEvidence` |
| `governor_academy_research` | `governor-academy-research/1` | `RecordStructuredProgressionEvidence` |
| `governor_war_academy_research` | `governor-war-academy-research/1` | `RecordStructuredProgressionEvidence` |

Pets and Masters are unsupported until a later explicit schema/fixture release.

## Scope and authority

The intake is scoped to one authorized Alliance roster entry. Protected write operations use existing Intelligence/Roster management authority. Authorization is reacquired at upload, review, duplicate resolution, commit, retry and delete, and the Roster destination independently reacquires authority before accepting reviewed meaning.

A stale review cannot write into a different Alliance, roster entry or Player target.

## Evidence provenance boundary

Roster validates Governor-specific reviewed Evidence through a dedicated Evidence-owned provenance lookup contract. Shared Evidence reference contracts used by Transfer and other families remain family-neutral and do not acquire Governor/Roster/dataset-specific methods.

The dedicated lookup verifies Evidence, Alliance, roster entry, Player, kind, schema, approved review and exact pinned dataset ID/checksum before destination persistence.

## Classification and extraction

The selected screenshot class is only a hint. Independent classification can return a different supported class or `unknown`; mismatch/unsupported UI is surfaced and fails closed.

Generic Charm inventory/collection text does not count as Governor Charms without Governor/Chief fixture-backed structure.

Extraction is schema-bound. Compound rows keep distinct normalized candidates: Gear quality is separated from level/mastery/star, and Charm observed name is separated from level. The full OCR line remains retained as provenance.

The structured extension recognizes explicit English `Buildings`, `Academy Research` and `War Academy Research` headings with `Building:` or `Technology:` rows. Level extraction requires an explicit `Level`/`Lv.` integer; absent levels remain unknown. Conflicting Academy/War Academy headings fail closed. These are narrow synthetic OCR contracts, not validation of arbitrary game layouts, image quality or languages.

## Normalization and automatic retry

Every normalization attempt records the exact Progression dataset ID/checksum used. Canonical Hero matching resolves against that immutable release only.

The earliest normalization attempt becomes the automatic-processing pin for the Evidence record, even if that attempt failed. All subsequent processing retries, queue redeliveries or process restarts reuse that same ID/checksum and fail closed if the pinned release cannot be loaded exactly. Automatic retry never silently uses a newer `latest()` release.

Normalization redelivery cannot reopen approved, committed, deleted or redacted Evidence. Active normalization uses the existing `extracting` lifecycle; retries from `failed` reacquire that state under lock, and the deletion guard blocks active processing. Review requests are rejected while processing/committing and after commit or redaction. Corrections to accepted observations use the Roster owner's correction workflow.

Moving an existing Evidence record to another Progression release would require an explicit re-normalization action; v1 does not provide one.

## Catalogue-backed validation

- Hero identity must resolve in the pinned Hero catalogue.
- Hero Gear enhancement and Mastery maxima come from pinned published tables.
- Governor Gear tier/star meaning reconciles to pinned Governor Gear steps when both values are present.
- Charm level is bounded by the pinned Charm ladder.
- Gear/Charm `slot_id` values are screen-local structural keys, not invented Progression entity identities.
- An OCR-visible Charm name remains Evidence provenance; v1 rejects synthetic `charm_id` destination input.
- Building and research review accepts only `states` rows. Reviewed subject IDs or exact case-insensitive labels must resolve in the pinned family; level and optional state ID must identify the same published state. Missing levels, unknown subjects and duplicate subjects are rejected.

Building and technology names remain normalized text candidates until review. The Roster validator resolves reviewed subjects to canonical IDs; normalization does not invent an identity or a missing level.

## Observation semantics

- Missing means unknown/not observed.
- Hero Detail says only what is visible about that Hero at `captured_at`.
- Hero Gear does not erase unseen gear slots.
- Governor Gear/Charm screenshots do not imply zero for unseen slots.
- Only `governor_hero_roster` may carry `complete_roster_capture=true`.
- A complete roster capture may establish Hero membership absence at that observation boundary; it does not erase previously observed per-Hero facts.
- Accepted observations retain Progression dataset ID/checksum and Evidence review provenance.

## Duplicate behavior

Exact, visual, semantic and destination-idempotency checks remain separate. Visual similarity is advisory. A semantic duplicate requires explicit supported resolution. A genuinely newer observation remains importable. Retry after destination success reuses the same owner receipt instead of appending duplicate Roster history.

Redaction removes classification OCR, extracted raw and normalized candidates, bounds/warnings, and Governor normalization payload copies. Dataset/attempt identity, immutable reviewed handoff and destination receipts remain. The summary therefore exposes redacted machine candidates while preserving accepted review meaning. A failed private-storage deletion fails the operation before provenance/path cleanup so an authorized retry can complete deletion.

## Current-state projection

`GovernorProgressionObservationQuery` composes append-only observations by newest fact while preserving observation IDs, Evidence/review IDs, captured times and dataset pins. Unobserved fields are not cleared by later partial screenshots.

The authorized screenshot workspace retains a 30-item limit and orders equal creation times by Evidence ID. PostgreSQL DISTINCT ON batches select only the latest classification, extraction, normalization and commit (creation time, then ID), and the latest review (revision number, then ID), within that list's Evidence IDs. Only fields from selected extractions are loaded. Database round trips remain constant as the list grows; historical attempts are not materialized into application memory. HARD-017 verifies the query budget and current-history semantics.

## UX contract

The owning Governor Progression workflow provides a mobile-first screenshot workspace for upload, processing status, class mismatch, extraction/normalization, low-confidence review, canonical Hero correction, captured-time confirmation, complete-roster confirmation, duplicate handling, before/after preview, explicit commit and destination receipt/recovery.

The screenshot remains privately authorized. Error/confidence semantics do not rely on colour alone; controls are keyboard accessible and localized.

## Fixture contract

Each schema maps to an executable corpus under `tests/Contexts/Intelligence/Evidence/Fixtures/GovernorProgression`. Corpora cover canonical, alternate-resolution, safe-crop, numeric-grouping, low-confidence, adjacent-number negative, missing-field, unsupported-UI, wrong-class, visual-duplicate, semantic-equal and semantic-newer cases. A field not fixture-proven/allowlisted cannot enter reviewed destination meaning.

`StructuredGovernorProgressionPipelineV3Test` exercises real upload, independent classification, extraction, pinned normalization, review provenance and Roster commit/replay with only the external OCR result substituted. It also covers missing levels, wrong-class stops and foreign actor/scope rejection. Release verification is tracked under HARD-010 in the hardening delivery ledger.

See also:

- `docs/product/screenshot-intake-governor-progression.md`
- `docs/architecture/contexts/intelligence/governor-progression-evidence.md`
- `docs/operations/screenshot-intake-governor-progression.md`
- `docs/product/factual-governor-progression.md`
