# Kingdoms automated ingestion operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-004` through `K4-P3` / Slice C  
**Current delivery:** Generic control plane plus delegated player-snapshot and game-Alliance factual observation promotion; no concrete source or scheduler/worker

## Runtime ownership

K4 remains first-party Laravel/PostgreSQL under `app/Domain/Kingdoms`. Production `config/kingdoms.php` intentionally contains an empty `ingestion_adapters` allowlist, so no real source can run in normal production state.

## First-party surfaces

The manager surface remains `/alliance/kingdom-ingestion/manage` with `kingdoms.manage` and recent password confirmation for human mutations. P2/P3 add no public route, inbound webhook, source-credential form, manual staging endpoint, or public promotion endpoint.

## Durable state

Operators may inspect subscriptions, batches, candidates, canonical player snapshots/game-Alliance observations, Audit, and internal outbox evidence. Promoted candidates record safe record type/ID/time. Machine-origin canonical history records bounded subscription/batch/adapter/source-record/hash provenance and a null User actor.

Do not rewrite captured Kingdom IDs, adapter versions, stable IDs, payload/identity hashes, or promoted-record references to force recovery.

## Promotion flows

### Player snapshots
A pending `player_snapshot` candidate on an active/current-Kingdom subscription must resolve one neutral player and one existing roster entry in the owning Alliance before the shared snapshot recorder accepts it.

### Game-Alliance observations
A pending `alliance_observation` candidate must resolve one active neutral game Alliance and one existing active tracking relation in the owning Alliance before the shared K3 observation recorder accepts it.

Machine game-Alliance observations are append-only. Tracking creation/reactivation and observation correction/invalidation are not recovery mechanisms and remain human-only.

## Diagnostics

Use safe Alliance/Kingdom/subscription/batch/candidate/promoted-record IDs, adapter key/version, source record ID, capture time, hashes, candidate state/reason, and internal audit/outbox IDs. Do not log normalized candidate bodies, source credentials, arbitrary raw responses, diplomacy/contact private text, or unrelated private data.

## Failure and recovery

- player identity/roster target failures: correct legitimate source/roster data outside promotion; never guess identity or auto-enroll.
- game-Alliance identity/tracking failures: establish/reactivate tracking only through the accepted human workflow if actually intended; never have ingestion do it.
- `kingdom_context_changed` / context mismatch: preserve history and stop; never retarget captured rows.
- `source_version_unapproved`: restore only through reviewed repository/config approval.
- business-record validation failure: inspect safe normalized fact bounds; do not coerce invalid facts into history.
- exact retry of a promoted candidate should resolve the same canonical record.

## Backup, migration and retention

P2 migration adds player-snapshot machine provenance. P3 migration `2026_08_11_210000_add_ingestion_provenance_to_alliance_observations.php` adds bounded game-Alliance observation provenance and Alliance/source-identity uniqueness.

Canonical promoted history has no FK dependency on operational K4 rows. Shared backup/restore applies; after restore, verify candidate→promoted-record correlation and continued independence of canonical history from operational candidate retention. Formal pruning remains K4-P5.

## Background processing

There is still **no K4 background processing**: no scheduler, queue acquisition job, source polling, cursor advancement, retry/backoff circuit, crawler/scraper/OCR/bot, or concrete external provider call. K4-P4 owns those concerns only after this P3 evidence head is accepted.

## Acceptance evidence

K4-P3 runtime candidate `8186af9fd7276a20889ca3a25b80172c6fe824d9` passed Dependency Review `31541291512`, CodeQL `31541291470`, and CI `31541291501`: Pint 515 files, PHPStan 365/365 zero errors, 417 tests / 9,628 assertions, frontend/build, migrations, immutable image, ephemeral staging, backup/restore, scan, and cleanup.

See [Slice C validation](../product/kingdoms-automated-ingestion-slice-c-validation.md) and [Slice C security review](../security/kingdoms-automated-ingestion-alliance-promotion-security-review.md).

## Stop conditions

Escalate rather than improvise when recovery would require unapproved source/network/credentials, raw-response archiving, cross-tenant access, stable-ID guessing, auto roster/tracking creation/reactivation, machine K3 correction/invalidation, transfer/diplomacy/contact mutation, scoring/recommendation, or a scheduler/worker before P4.
