# GameWorld / Progression

Status: Active delivery — 2026-08-23

## Responsibility

`GameWorld/Progression` owns immutable/versioned factual KingShot progression catalogue truth. It exists so Hero, gear, building, research, Pet, Master and other progression facts can be consumed by several capabilities without duplicating or guessing game data.

It does not own a Governor's observed state or tactical planning intent.

```text
Factual KingShot catalogue release
  -> GameWorld/Progression

Observed Governor state
  -> Intelligence/Roster

Saved Hero / troop formation intent
  -> Operations/Rallies (or the applicable Operations owner)
```

## Release boundary

Published releases live as reviewed immutable resources below `resources/data/progression/<release-id>/`. A release currently comprises:

- `release.json` — release identity, source registry, conflicts and per-family dispositions;
- `heroes.json` — canonical Hero identities and scoped provenance;
- `systems.json` — normalized factual progression-system values/summaries;
- `formations.json` — source-scoped community troop-ratio conventions.

`ProgressionDatasetQuery` is the authoritative runtime reader. It validates schema, source references, family dispositions, Hero identity uniqueness, formation ratios and the prohibition on recommendation semantics before returning a typed `ProgressionDataset`.

The release checksum is deterministic over the committed release files. A consumer can pin `progression_dataset_id` plus checksum to preserve historical meaning. Existing release files are never modified after a release is treated as published; corrected or expanded source meaning is represented by a new release directory/version.

## Source/evidence model

Source authority is descriptive rather than a magic trust score:

- Tier A — first-party/official material or reviewed in-game evidence;
- Tier B — maintained structured community reference;
- Tier C — inspectable open-source/community datasets;
- Tier D — community discussion/observation.

A Tier D formation convention is useful factual evidence that a convention exists. It is not proof that the formation is optimal.

Conflicting values remain explicit release conflicts. A conflict can be displayed by the factual library while the stricter calculator evidence gate stays closed.

## Consumer rules

- `Intelligence/Roster` may store canonical Hero IDs, the release ID/checksum and observed level/star/Widget fields in append-only observations. It cannot edit catalogue values.
- `Operations/Rallies` may store canonical Hero IDs and the release ID/checksum on a saved Governor formation. That row remains user planning intent.
- Cross-context integrations carry scalar IDs/checksums or value objects; they do not expose Progression persistence models because the release is an immutable file-backed catalogue.
- Runtime HTTP requests never scrape public/community sources.

## Failure behavior

A malformed release, missing required family disposition, unresolved source reference, duplicate Hero ID, invalid formation ratio or recommendation-like formation field fails closed during load. A requested pinned checksum mismatch fails validation so an old observation/loadout cannot silently acquire new meaning.

## Calculator boundary

Progression catalogue availability does not itself authorize calculator implementation. Calculator-target numeric families remain subject to the global source/version/unit/reconciliation gate in the product delivery ledger. Unknown, conflicting or insufficiently sourced numeric rows cannot be synthesized to satisfy that gate.
