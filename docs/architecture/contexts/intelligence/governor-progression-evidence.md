# Intelligence / Evidence — Governor Progression Screenshots

Status: Active delivery — 2026-08-26

## Responsibility

Governor Progression screenshot intake is an explicit `Intelligence/Evidence` family. Evidence owns the private screenshot, security-scan result, OCR/classification/extraction attempts, dataset-pinned normalization attempts, immutable human review revisions, duplicate decisions, commit attempts, retry/recovery state and the destination receipt.

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

## Explicit scope

Governor Progression Evidence is scoped by `alliance_id` + `roster_entry_id`. Bear Hunt occurrence references and Transfer Plan/participant references are absent. Application validation and database constraints enforce the mutually exclusive scope shape.

This is not a generic target framework. No `target_type`/`target_id`, arbitrary domain class, generic Governor OCR bounded context or unconstrained extracted-field destination exists.

Every protected upload/review/duplicate/commit/retry/delete operation re-resolves the active Alliance scope and target roster entry. A material target change after review invalidates the reviewed handoff; Evidence never silently retargets approved meaning.

## Supported v1 schemas

The only Governor Progression screenshot classes currently supported are:

- `governor_profile` — `governor-profile/1`;
- `governor_hero_roster` — `governor-hero-roster/1`;
- `governor_hero_detail` — `governor-hero-detail/1`;
- `governor_hero_gear` — `governor-hero-gear/1`;
- `governor_gear` — `governor-gear/1`;
- `governor_charms` — `governor-charms/1`.

Pets and Masters are not implicitly accepted by these schemas. They require future explicit schemas and fixture corpora.

The user-selected expected class is a hint. `GovernorProgressionEvidenceClassifier` independently selects a supported class or `unknown`. A mismatch is surfaced and cannot be routed blindly into the selected extractor.

`GovernorProgressionEvidenceExtractor` is schema-bound. It may emit only fields allowlisted by the registered schema. Fixture corpora under `tests/Fixtures/Evidence/GovernorProgression` are executable allowlist proofs rather than documentation-only names.

## Progression normalization boundary

`GameWorld/Progression` is a read-only dependency of normalization and destination validation. Each normalization attempt pins:

- Progression dataset ID;
- immutable dataset checksum;
- normalizer key/version;
- normalized field candidate;
- canonical identity candidate when available;
- identity confidence;
- warnings.

Machine OCR/extraction values remain immutable attempt output. Human corrections create reviewed meaning; they do not rewrite the machine attempt or its confidence.

If the latest Progression release changes after normalization, existing Evidence is not silently reinterpreted. Re-normalization is a new explicit attempt against another immutable release. Accepted Roster observations retain their original dataset ID/checksum.

Screenshot Intake cannot create, rename, merge, correct or infer canonical Progression entities or facts.

## Review and handoff

All six v1 classes require human review. The review surface exposes the screenshot, expected/detected class, classification confidence, schema/fixture version, raw OCR, normalized candidates, field confidence/warnings, canonical identity match, pinned dataset, captured time, completeness semantics, duplicate state and destination preview.

Approved meaning is a closed typed union for the selected schema. Unknown keys are rejected. Missing fields remain unobserved; they are not converted to zero or inferred from neighbouring numbers.

The six public destination Actions are:

- `RecordGovernorProfileEvidence`;
- `RecordHeroRosterEvidence`;
- `RecordHeroDetailEvidence`;
- `RecordHeroGearEvidence`;
- `RecordGovernorGearEvidence`;
- `RecordGovernorCharmsEvidence`.

The Actions reacquire current Roster authority and delegate owner persistence/invariants to the Roster observation writer. Cross-context contracts contain scalar IDs/value objects only.

## Duplicate and idempotency semantics

Four different controls remain separate:

1. exact Evidence duplicate — binary identity inside the authorized Governor/Roster scope;
2. visual duplicate — advisory similarity warning, still reviewable;
3. semantic duplicate — equivalent reviewed meaning in the same owner scope, requiring explicit supported resolution;
4. destination idempotency — one immutable approved review maps to one Roster receipt and can be safely replayed.

A genuinely newer observation remains importable. Destination success followed by Evidence acknowledgement failure is recovered by replaying the same destination idempotency key and recording the already-created receipt.

## Deletion and retention

Deleting/redacting Evidence never deletes an accepted Roster observation. Evidence retention may remove binary/OCR/raw sensitive material while retaining the minimum review/commit/receipt provenance needed to explain the handoff. Roster correction/removal is a separate explicit owner operation.

## Consumer rule

Downstream product surfaces consume authorized `Intelligence/Roster` queries/read models, not Evidence tables. `GameWorld/Progression` supplies catalogue interpretation only. This keeps observed current state, factual catalogue truth and planning/strategy intent separate.

The complete product and acceptance contract is `docs/product/screenshot-intake-governor-progression.md`.
