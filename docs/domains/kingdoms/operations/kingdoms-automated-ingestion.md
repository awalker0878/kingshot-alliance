# Kingdoms automated ingestion operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-004` through `K4-P2` / Slice B  
**Current delivery:** Generic control plane plus existing-roster player-snapshot promotion; no concrete source or scheduler/worker

## Runtime ownership

K4 remains first-party Laravel/PostgreSQL under `app/Domain/Kingdoms`. Production `config/kingdoms.php` intentionally contains an empty `ingestion_adapters` allowlist, so no real source can run in normal production state.

## First-party surfaces

The existing manager surface remains `/alliance/kingdom-ingestion/manage` with `kingdoms.manage` and recent password confirmation for human mutations. Slice B adds no public route, inbound webhook, source-credential form, manual staging endpoint, or public promotion endpoint.

## Durable state

Operators may inspect subscriptions, batches, candidates, canonical player snapshots, Audit, and internal outbox evidence. Promoted candidates record safe record type/ID/time. Machine-origin snapshots record bounded subscription/batch/adapter/source-record/hash provenance and a null User actor.

Do not rewrite captured Kingdom IDs, adapter versions, stable IDs, payload/identity hashes, or promoted-record references to force recovery.

## Promotion flow

Internal promotion is valid only for a pending `player_snapshot` candidate on an active/current-Kingdom subscription whose registered adapter version still matches. Stable game-player ID must resolve to one neutral player and one existing roster entry in the owning Alliance. The shared snapshot recorder performs final observation validation and persistence.

No name matching or roster creation is an operational recovery mechanism.

## Diagnostics

Use safe Alliance/Kingdom/subscription/batch/candidate/snapshot IDs, adapter key/version, source record ID, capture time, hashes, candidate state/reason, and internal audit/outbox IDs. Do not log normalized candidate bodies, source credentials, arbitrary raw responses, or unrelated private data.

## Failure and recovery

- `unknown_player`, `ambiguous_player_identity`, `roster_target_missing`, or `ambiguous_roster_target`: correct the legitimate roster/source relationship outside promotion; never guess identity.
- `kingdom_context_changed` or `candidate_context_mismatch`: preserve historical rows and stop; never retarget them.
- `source_version_unapproved`: restore only through reviewed repository/config adapter approval; never bypass by database edits.
- `snapshot_validation_failed`: inspect safe field-level source contract and bounds; do not coerce invalid facts into history.
- exact retry of an already promoted candidate should resolve the same snapshot.

## Backup, migration and retention

Migration `2026_08_11_200000_add_ingestion_provenance_to_player_snapshots.php` makes User actor nullable for legitimate machine observations, adds bounded snapshot provenance/Alliance source-identity uniqueness, and adds safe candidate promotion references.

Shared backup/restore procedures apply. After restore, verify candidate→snapshot correlation and that canonical promoted snapshot history survives independently of operational ingestion rows. Formal operational pruning policy remains K4-P5.

## Background processing

There is still **no K4 background processing**: no scheduler, queue acquisition job, source polling, cursor advancement, retry/backoff circuit, crawler/scraper/OCR/bot, or concrete external provider call. K4-P4 owns those concerns only after both promotion paths are validated.

## Acceptance evidence

K4-P2 runtime candidate `37a7df3e0e88e2303f3c8fa74efaaed0b85fbd4f` passed Dependency Review `31538958810`, CodeQL `31538958745`, and CI `31538958920`: Pint 512 files, PHPStan 364/364 zero errors, 412 tests / 9,564 assertions, frontend/build, migrations, immutable image, ephemeral staging, backup/restore, scan, and cleanup.

See [Slice B validation](../product/kingdoms-automated-ingestion-slice-b-validation.md) and [Slice B security review](../security/kingdoms-automated-ingestion-player-promotion-security-review.md).

## Stop conditions

Escalate rather than improvise when recovery would require unapproved source/network/credentials, raw-response archiving, cross-tenant access, stable-ID guessing, roster auto-creation, game-Alliance promotion before P3, transfer/diplomacy mutation, scoring/recommendation, or a scheduler/worker before P4.
