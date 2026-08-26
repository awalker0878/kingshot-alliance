# Screenshot Intake: Governor Progression Reference

Status: Active delivery — 2026-08-26

## Purpose

Governor Progression Screenshot Intake converts supported KingShot screenshots into reviewed, append-only `Intelligence/Roster` observations. It never imports screenshot content into `GameWorld/Progression`; the Progression dataset is only an immutable factual reference used to normalize and validate observed identities and bounds.

## Supported screenshot classes

| Evidence kind | Schema | Destination |
| --- | --- | --- |
| `governor_profile` | `governor-profile/1` | `RecordGovernorProfileEvidence` |
| `governor_hero_roster` | `governor-hero-roster/1` | `RecordHeroRosterEvidence` |
| `governor_hero_detail` | `governor-hero-detail/1` | `RecordHeroDetailEvidence` |
| `governor_hero_gear` | `governor-hero-gear/1` | `RecordHeroGearEvidence` |
| `governor_gear` | `governor-gear/1` | `RecordGovernorGearEvidence` |
| `governor_charms` | `governor-charms/1` | `RecordGovernorCharmsEvidence` |

Pets and Masters are unsupported until a later explicit schema/fixture release.

## Scope and authority

The intake is scoped to one authorized Alliance roster entry. The current protected write boundary is the existing Intelligence/Roster management authority. Authorization is reacquired at upload, review, duplicate resolution, commit, retry and delete, and the Roster destination reacquires authority before accepting a reviewed observation.

A Player cannot use a stale approved review to write into a different Alliance, roster entry or Player target.

## Normalization

Every normalization attempt records the exact Progression dataset ID/checksum used. Canonical Hero matching resolves against that immutable dataset only. An unknown/misspelled Hero may be offered as an unresolved candidate, but review must select a valid canonical Hero identity before a destination payload requiring one can commit.

A later Progression release does not rewrite an older normalization or accepted observation. Re-normalization is an explicit new machine attempt.

## Observation semantics

- Missing means unknown/not observed.
- A Hero Detail screenshot says only what is visible about that Hero at `captured_at`.
- A Hero Gear screenshot does not erase gear slots not shown.
- A Governor Gear/Charm screenshot does not imply zero for unseen slots.
- Only `governor_hero_roster` may carry `complete_roster_capture=true`.
- A complete roster capture can establish Hero membership absence for previously known Heroes at that observation boundary; it does not erase previously observed per-Hero progression facts.
- All accepted observations preserve their Progression dataset ID/checksum and Evidence review provenance.

## Duplicate behavior

Exact, visual, semantic and destination-idempotency checks solve different problems. Visual similarity is advisory. A semantic duplicate requires an explicit supported resolution. A genuinely newer observation remains importable. A retry after destination success returns/reuses the same owner receipt instead of appending duplicate Roster history.

## Current-state projection

`GovernorProgressionObservationQuery` composes append-only observations into current state by applying the newest observed value for each fact while preserving source observation IDs, Evidence/review IDs, captured times and dataset pins. Unobserved fields are not cleared by a later partial screenshot.

## UX contract

The owning Governor Progression workflow provides a mobile-first screenshot workspace for upload, processing status, class mismatch, extraction/normalization, low-confidence review, canonical identity correction, captured-time confirmation, complete-roster confirmation, duplicate handling, before/after preview, explicit commit and destination receipt.

The screenshot itself remains privately authorized. Error/confidence semantics cannot rely on colour alone and all interactive controls must be keyboard accessible and localized.

## Fixture contract

Each schema name maps to an executable corpus under `tests/Fixtures/Evidence/GovernorProgression`. The corpora cover canonical, alternate-resolution, safe-crop, numeric-grouping, low-confidence, adjacent-number negative, missing-field, unsupported-UI, wrong-class, visual-duplicate, semantic-equal and semantic-newer cases. A field not proven/allowlisted by the schema cannot be emitted into reviewed destination meaning.

See also:

- `docs/product/screenshot-intake-governor-progression.md`
- `docs/architecture/contexts/intelligence/governor-progression-evidence.md`
- `docs/operations/screenshot-intake-governor-progression.md`
- `docs/product/factual-governor-progression.md`
