# KINGDOMS-004 Slice D scheduler/replay security review

[← Kingdoms security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Kingdoms  
**Capability:** Generic acquisition scheduler, cursor, retry/circuit and manager replay (`K4-P4`)  
**Code owner:** `app/Domain/Kingdoms`

## 1. Scope and security objective

This review covers generic scheduler/queue/cursor/retry/replay mechanics around the already accepted K4 ingestion and promotion contracts. It does not approve a concrete source, endpoint, credential, scraper/OCR/browser/game-client automation, public inbound interface, or production cutover.

## 2. Assets and sensitive data

Protected assets include Alliance/current-Kingdom context; source subscription/version approval; opaque source cursor/window identity; normalized candidate identity; canonical player/game-Alliance observation history; scheduler claim/health/circuit state; and internal audit/outbox evidence.

Source credentials, authorization headers/cookies, arbitrary raw responses and provider-private material remain outside the Kingdoms persisted scheduler contract.

## 3. Trust boundaries

A scheduler tick crosses shared Platform scheduling into tenant-owned Kingdoms work. A queue worker crosses approved adapter acquisition into normalized staging and accepted P2/P3 promotion actions. Manager replay crosses a human assurance boundary back into existing promotion rules.

Neither queue identity, adapter identity nor neutral game identity grants tenant authority. Every run re-resolves the Alliance-owned subscription/current Kingdom and approved adapter version.

## 4. Threats and controls

### Duplicate scheduler/work dispatch
Repeated scheduler ticks could create duplicate source work. **Control:** due work is claimed under row lock and `next_run_at` advances before dispatch; jobs are unique and use per-subscription `WithoutOverlapping` middleware.

### Cursor rewind or replay multiplication
At-least-once delivery could duplicate business history. **Control:** unique source-window state, deterministic candidate identity, stored next cursor, cursor advancement only after completed/partial outcomes, and accepted P2/P3 promoted-record idempotency.

### Source removal or Kingdom drift
Queued work could run after source revocation or tenant context change. **Control:** worker re-resolves adapter/version and current Alliance Kingdom before acquisition/promotion; stale work fails closed and pending batch state may be blocked rather than retargeted.

### Failure-detail leakage
External exceptions could persist credentials/URLs/raw response fragments. **Control:** scheduler state stores bounded codes only (`acquisition_failed`, `source_contract_invalid`, `processing_validation_failed`, `retry_exhausted`, context/source codes); raw exception text is not copied to DB/UI/audit/outbox.

### Unbounded retry / external pressure
A failing source could create uncontrolled traffic. **Control:** adapter poll interval is bounded, page size is bounded, queue tries/timeouts/backoff are bounded, repeated failures open a bounded circuit, and a dedicated low-concurrency queue isolates source work.

### Human replay abuse
A manager could use replay to bypass target/identity controls. **Control:** replay requires `kingdoms.manage`, recent password confirmation, owning Alliance/current Kingdom, active subscription, currently approved adapter/version and a quarantined candidate; replay delegates to P2/P3 promotion rather than writing canonical rows directly.

### Canonical-history corruption
Scheduler code could silently write or correct K1/K3 history. **Control:** P4 invokes existing stage and promotion actions; no direct player-snapshot/game-Alliance observation write path is introduced, and machine K3 correction/invalidation remains unavailable.

## 5. Authorization, tenancy and privacy

Human replay remains password-confirmed manager work. Background processing has no fabricated User actor; machine provenance remains explicit and bounded. Tenant-owned rows are always looked up beneath the subscription/Alliance context before mutation.

The management surface may expose adapter key/version, state, cursor, scheduling/circuit timing and bounded failure/quarantine codes. It does not expose candidate normalized payload bodies or source secrets/raw responses.

## 6. Integrity, replay and concurrency

Database locks guard scheduler claims, subscription context and cursor advancement. Queue unique/overlap controls reduce duplicate execution but database/candidate/business idempotency remains authoritative if cache/worker delivery is at least once.

A completed/partial source window may be replayed only when the adapter returns the same stored next cursor. A divergent next cursor for the same source window fails closed.

## 7. Secret and data lifecycle

P4 introduces no credential storage and production adapter configuration remains empty. Opaque cursors and bounded scheduler failure state are operational metadata subject to P5 retention/review hardening.

Canonical promoted history remains independent from operational K4 row retention.

## 8. Abuse limits and failure behavior

Acquisition pages are limited to 250 records and adapter polling to 60–86,400 seconds. Dedicated queue concurrency is intentionally small. Queue timeout/retries/backoff are finite, and circuit state prevents immediate repeat acquisition after repeated failure.

Unknown/stale/revoked/invalid target/source/context cases remain quarantine/block/fail-closed conditions rather than fallback matching or auto-creation.

## 9. Verification and evidence

Runtime candidate `27855f79ba128b35edea7f82b2f6381fbf810363` passed Dependency Review `31545866277`, CodeQL `31545866288`, and CI `31545866249`: Pint 523 files, PHPStan/Larastan 371/371 with zero errors, 423 tests / 9,697 assertions, frontend/build, migrations, immutable image, staging, backup/restore and scan.

Focused tests cover duplicate scheduler claim prevention, mixed target processing, exact source-window retry, bounded circuit state, exhausted-job finalization, password-confirmed manager replay and migration round-trip.

## 10. Residual risks and external controls

A future real source adds authorization/terms, network egress, DNS/redirect/private-address, TLS, secret rotation, provider rate-limit, schema/version and revocation risks that generic P4 cannot prove with an empty production allowlist.

K4-P5 must harden operational review, metrics/alerts, retention/pruning and source-revocation procedures. Repository P4 acceptance still does not approve a real source or production cutover.
