# Intelligence / Evidence — Governor Progression Screenshots

Status: Active delivery — final immutable-candidate verification pending

## Responsibility

Governor Progression screenshot intake is an explicit `Intelligence/Evidence` family. Evidence owns the private screenshot, security-scan result, OCR/classification/extraction attempts, dataset-pinned normalization attempts, immutable human review revisions, duplicate decisions, commit attempts, retry/recovery state and destination receipt.

Evidence does not own accepted Governor progression state and never owns or mutates canonical KingShot progression truth.

```text
private screenshot
  -> Intelligence/Evidence
  -> OCR + independent class verification
  -> schema-bound extraction
  -> normalization against pinned GameWorld/Progression release
  -> authorized human review
  -> explicit Intelligence/Roster destination Action
  -> append-only Roster observation + scalar receipt
```

## Context boundaries

- `Intelligence/Evidence` owns source Evidence, machine/review provenance and commit coordination.
- `GameWorld/Progression` is a read-only factual catalogue dependency used for pinned normalization and destination validation.
- `Intelligence/Roster` owns accepted append-only Governor progression observations and current-state/history composition.
- No Governor OCR bounded context, generic OCR ingestion framework, generic `target_type`/`target_id` abstraction or unconstrained field-bag destination exists.

Cross-context calls carry scalar IDs/value objects only. Evidence never imports foreign-context persistence models.

## Evidence interface segregation

Governor Progression review provenance is exposed through a dedicated Evidence-owned `GovernorProgressionEvidenceReferenceLookup` contract. The existing family-neutral Evidence reference interface used by Transfer and other consumers remains unchanged by Governor concepts.

The Governor-specific contract validates the exact approved review, Evidence ID, Alliance, roster entry, Player, Evidence kind, schema version and Progression dataset ID/checksum before Roster accepts a destination write. This preserves strict provenance without coupling other Evidence families to Governor semantics.

## Explicit scope

Governor Progression Evidence is scoped by `alliance_id` + `roster_entry_id`. Bear Hunt occurrence and Transfer Plan/participant references are absent. Application validation and database constraints enforce the mutually exclusive scope shape.

Every protected upload/review/duplicate/commit/retry/delete operation re-resolves active Alliance scope and target roster entry. Material target drift after review invalidates the handoff; Evidence never silently retargets approved meaning.

## Supported v1 schemas

The only supported classes are:

- `governor_profile` — `governor-profile/1`;
- `governor_hero_roster` — `governor-hero-roster/1`;
- `governor_hero_detail` — `governor-hero-detail/1`;
- `governor_hero_gear` — `governor-hero-gear/1`;
- `governor_gear` — `governor-gear/1`;
- `governor_charms` — `governor-charms/1`.

Pets, Masters and other panels require future explicit schemas/fixtures.

The user-selected expected class is a hint. `GovernorProgressionEvidenceClassifier` independently selects a supported class or `unknown`. Generic Charm inventory/collection wording does not classify as Governor Charms without explicit Governor/Chief structure. Mismatches fail closed rather than blindly routing to the expected extractor.

`GovernorProgressionEvidenceExtractor` is schema-bound and fixture-proven. Adjacent Gear quality/level/mastery/star and Charm name/level values are split into separate candidates while the complete raw OCR line remains provenance.

## Progression normalization and retry pinning

Each normalization attempt records Progression dataset ID/checksum, normalizer key/version, normalized candidates, canonical identity candidate/confidence and warnings.

The first normalization attempt establishes the automatic-processing dataset pin even when it fails. Subsequent processing retry, queue redelivery or process restart reuses the earliest attempt's dataset ID/checksum and must load that exact immutable release. Automatic retry never falls forward to `latest()` after normalization history exists.

Moving Evidence to a newer dataset would be a distinct explicit re-normalization action; v1 does not provide one. Existing attempts and accepted Roster observations remain pinned to their original release.

Machine OCR/extraction output is immutable attempt history. Human corrections create new reviewed meaning and never rewrite machine output/confidence.

## Catalogue validation versus structural observation

Destination validation uses the pinned Progression release only where that release exposes canonical meaning:

- Hero identities must exist in the pinned Hero catalogue.
- Hero progression values obey closed bounds.
- Hero Gear enhancement and Mastery maxima are derived from pinned published tables.
- Governor Gear tier/star values reconcile to pinned Governor Gear steps when those fields are present together.
- Charm level is bounded by the pinned Governor Charm ladder.
- Hero Gear, Governor Gear and Charm `slot_id` values are closed screen-local structural keys, not invented Progression entity identities.
- OCR-visible Charm names remain Evidence provenance in v1; a synthetic `charm_id` cannot cross into Roster.

Screenshot Intake cannot create, rename, merge, correct or infer canonical Progression entities/facts.

## Review and handoff

All six v1 classes require human review. The review surface exposes expected/detected class/confidence, schema/fixture version, raw OCR, normalized candidates, field confidence/warnings, canonical Hero match, pinned dataset, captured time, completeness semantics, duplicate state and destination preview.

Approved meaning is a closed typed union. Unknown keys are rejected. Missing fields remain unobserved.

The six destination Actions are:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

Each action reacquires current Roster authority, validates exact Evidence/review provenance and pinned dataset, and delegates owner persistence/idempotency to the Roster writer.

## Duplicate and idempotency semantics

Four controls stay distinct:

1. exact Evidence duplicate — binary identity inside authorized Governor/Roster scope;
2. visual duplicate — advisory similarity warning, still reviewable;
3. semantic duplicate — equivalent reviewed meaning requiring explicit supported resolution;
4. destination idempotency — one immutable approved review maps to one Roster receipt and safely replays.

A genuinely newer observation remains importable. Destination success followed by Evidence acknowledgement failure recovers by replaying the same destination key and recording the already-created receipt.

## Deletion, retention and consumers

Deleting/redacting Evidence never deletes accepted Roster observations. Evidence retention may remove binary/OCR/raw sensitive material while retaining minimum handoff provenance. Roster correction/removal is a separate explicit owner operation.

Downstream product surfaces consume authorized `Intelligence/Roster` queries/read models, never Evidence candidate tables. `GameWorld/Progression` supplies catalogue interpretation only.

The complete product and acceptance contract is `docs/product/screenshot-intake-governor-progression.md`.