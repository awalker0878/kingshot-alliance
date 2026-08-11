# Kingdoms automated ingestion operations

[← Kingdoms operations](README.md)

**Scope:** `KINGDOMS-004` `K4-P1` / Slice A  
**Current delivery:** Runtime foundation validated; no concrete source, scheduler, worker, or observation promotion

## Runtime ownership

Slice A is first-party Laravel/PostgreSQL control-plane functionality under `app/Domain/Kingdoms`. Human management is synchronous. Internal batch/candidate actions exist as later-worker contracts, but no background ingestion process invokes an external provider yet.

Production configuration `config/kingdoms.php` intentionally contains an empty `ingestion_adapters` allowlist. Therefore a normal production manager cannot create a source subscription until a concrete adapter receives separate approval and is registered by repository/operator configuration.

## First-party routes

- manager status/control: `GET /alliance/kingdom-ingestion/manage`;
- create approved subscription: `POST /alliance/kingdom-ingestion/subscriptions`;
- transition subscription state: `PATCH /alliance/kingdom-ingestion/subscriptions/{subscription}/state`;
- reject quarantined candidate: `POST /alliance/kingdom-ingestion/subscriptions/{subscription}/candidates/{candidate}/reject`.

All routes require authenticated/verified active-Alliance context. The management page/actions require `kingdoms.manage`; mutations are inside recent password confirmation middleware.

There is no public API, inbound webhook, generic endpoint editor, source-credential form, manual batch-staging route, or public candidate payload endpoint.

## Durable state

Operators may inspect:

- `kingdom_ingestion_subscriptions` — captured Alliance/Kingdom/adapter/version/state/cursor/health;
- `kingdom_ingestion_batches` — safe source-window identity, state/counters/timing/failure code;
- `kingdom_ingestion_candidates` — bounded normalized facts, hashes, target/state/reason;
- `audit_events` — attributable human management evidence; and
- `outbox_messages` — internal `kingdoms.ingestion_*` durability events.

Do not insert/edit these rows manually to recover a source problem. In particular, never change captured `kingdom_id`, identity hashes, candidate normalized facts, or completed batch outcomes to force progress.

## Adapter and secret boundary

An adapter is executable code registered in configuration, not a tenant-supplied URL. Slice A stores no endpoint URL, authorization header, cookie, API key, password, recovery secret, or canonical raw source response.

A real networked adapter must not be enabled merely by populating the config array. It requires the K4 source-approval record: source/transport owner, permission basis, adapter/version, field/stable-ID contract, network boundary, secret owner, rate/timeout/cursor behavior, and revocation/schema-change procedure.

## Normal flow

With an approved adapter registered, a manager may create one subscription for the Alliance/current-Kingdom/adapter key and transition it among active/paused/disabled. Internal processing may start one batch/window, normalize/stage candidates, quarantine missing stable identity, and complete the batch.

Slice A stops there. `pending` candidates are not automatically promoted. Managers may reject quarantined candidates; safe replay/promotion is intentionally a later slice.

## Health and diagnostics

Use safe identifiers and counts:

- Alliance and captured/current Kingdom IDs;
- subscription ID, adapter key/version/state;
- last success/failure/blocked timestamps and bounded block reason;
- batch ID/state/window, received/staged/quarantined/rejected counts, timing, failure code;
- candidate ID/target/state, stable/source record ID, capture time, quarantine/rejection code;
- request/trace/audit/outbox IDs.

Do not emit normalized payload bodies, source secrets, arbitrary external responses, private manager text, or high-cardinality secret-bearing values into logs/metrics/support tickets.

## Failure modes and recovery

### No approved adapters

This is the expected production Slice A state. The manager page shows no approved source adapters; do not bypass it with database inserts or arbitrary HTTP calls.

### Alliance-Kingdom drift

Historical subscription/batch/candidate state remains diagnosable. New batch/staging/re-activation fails closed. Disable the old subscription if needed; never rewrite captured Kingdom context.

### Missing stable identity

Candidate state is quarantined with a stable reason. Do not name/tag-match or manually set a guessed stable ID. Later K4 replay rules may reprocess a valid source record after the source/target relationship is legitimately established.

### Duplicate source window/candidate

Exact retry should resolve to existing durable identity. Treat duplicate-key/manual identity rewrite pressure as a defect; do not delete history to make a retry pass.

### Failed/blocked batch

Use the bounded failure code and safe operational context. A completed outcome is immutable. Network/source retry behavior is not implemented in Slice A.

## Backup, migration and rollback

Migration `2026_08_11_190000_create_kingdom_ingestion_foundation.php` creates candidates after batches after subscriptions; rollback reverses that order. The Kingdom migration round-trip test now tears K4 down before older Kingdoms tables and reapplies it after them.

Shared PostgreSQL backup/restore and immutable-image rollback procedures apply. After restore, verify representative subscription/batch/candidate ownership/state/hash context and that no unexpected K1/K3 observation was created from Slice A state.

## Background processing

There is **no K4 background processing yet**: no scheduler registration, queue job, worker partition, external acquisition, cursor advancement loop, retry/backoff/circuit behavior, or replay worker. Those are `K4-P4` concerns after player/game-Alliance promotion slices are validated.

Do not operationalize an ad-hoc cron/curl/scraper/bot around the internal actions as a substitute for the missing reviewed worker contract.

## Acceptance evidence

Exact Slice A runtime candidate: `5a37731374e9fa7aef591b7b1badd9cc13603e2c`.

Protected Dependency Review `31533284318`, CodeQL `31533284195`, and CI `31533284398` passed. CI includes 509 Pint files, PHPStan/Larastan 363/363 with zero errors, 407 tests / 9,466 assertions, frontend checks/build, PostgreSQL migrations, immutable production image, ephemeral staging, backup/restore, image vulnerability scan and cleanup.

See [Slice A validation](../product/kingdoms-automated-ingestion-slice-a-validation.md).

## Stop conditions

Escalate rather than improvising when recovery would require an unapproved source/URL, source credentials in Kingdoms state, raw-response archiving, cross-tenant access, stable-ID guessing, manual observation promotion, auto roster/tracking creation, transfer/diplomacy mutation, scoring/recommendation, or a new scheduler/worker before its owning slice.

Repository validation of this foundation does not approve real production source enablement or real production cutover.
