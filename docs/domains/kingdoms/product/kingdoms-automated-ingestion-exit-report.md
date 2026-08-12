# KINGDOMS-004 automated game-data ingestion exit report

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope:** `KINGDOMS-004`  
**Acceptance phase:** `K4-P6` — whole-increment acceptance  
**Status:** **Accepted**  
**Validated implementation SHA:** `3e0976e8bdd32207bd6314011c26b94fa0f3c118`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003`

## 1. Acceptance decision

`KINGDOMS-004` is **Accepted** as a repository/product capability for governed automated ingestion from separately approved machine-readable sources.

The accepted increment provides an empty-by-default, code/config-allowlisted ingestion control plane; Alliance/current-Kingdom subscriptions; bounded source windows and normalized candidates; deterministic identity/quarantine/rejection; delegated factual player/game-Alliance promotion to existing tenant-owned targets; scheduled acquisition/cursor/retry/replay; source-revocation reconciliation; bounded operational retention; payload-free health monitoring; and realistic-volume capacity evidence.

Acceptance does **not** approve a concrete production source, provider terms, endpoint, network path, source credential, scraper/OCR/browser/game-client automation, public inbound API/webhook, production source enablement, or production cutover.

## 2. Accepted source and acquisition boundary

The whole-increment review confirms:

- production `config/kingdoms.php` contains an empty `ingestion_adapters` allowlist by default;
- adapters are repository/operator code/config registrations rather than tenant-entered URLs, headers, cookies or credentials;
- one approved adapter key/version is captured on each subscription and source window;
- acquisition pages are bounded to at most 250 normalized records;
- repository-controlled poll intervals are bounded to 60–86,400 seconds;
- raw responses are not a canonical Kingdoms archive;
- source secrets and arbitrary endpoint configuration are excluded from Kingdoms persistence and manager surfaces; and
- removal or version drift of an adapter fails closed instead of silently substituting or continuing.

The P6 whole-increment acceptance test begins by proving the production allowlist is empty, enables only a test-local adapter, and later removes that adapter to exercise the accepted revocation path.

## 3. Accepted identity, tenancy and promotion boundary

Automatic identity remains stable-ID-only within one captured/current Kingdom:

- player promotion resolves only by stable game-player ID;
- game-Alliance promotion resolves only by stable game-Alliance ID;
- names, tags, handles, source labels and row positions never auto-match identity;
- every machine run re-resolves owning Alliance, current Kingdom and source version;
- player promotion requires exactly one existing owning-Alliance roster relation;
- game-Alliance promotion requires exactly one existing active owning-Alliance tracking relation; and
- missing, ambiguous, inactive, stale-context or otherwise invalid targets fail closed/quarantine before business mutation.

Promotion delegates through the accepted K1 `RecordPlayerSnapshot` and K3 game-Alliance observation contracts. It does not create/reactivate roster, tracking, membership, transfer, diplomacy or contact state and does not fabricate a human User actor.

## 4. Accepted factual-history and provenance behavior

Promoted machine facts remain append-oriented canonical Kingdoms history.

`PlayerSnapshot` and `KingdomAllianceObservation` copy bounded provenance independently of K4 operational rows, including source subscription/batch, adapter key/version, source record ID and deterministic identity/payload hashes. Exact promoted-candidate retry returns the existing canonical record instead of multiplying history.

Machine game-Alliance promotion cannot correct or invalidate accepted observations. Human correction/invalidation remains the accepted K3 path.

The P6 acceptance test promotes both target kinds in one source window and verifies the copied provenance before and after operational candidate/batch deletion.

## 5. Scheduler, cursor, retry and concurrency review

K4 scheduling is accepted with the following bounds:

- `kingdoms:queue-ingestion --limit=100` runs every minute;
- due claims are rechecked transactionally under row locks;
- jobs run on isolated Horizon queue `kingdoms-ingestion`;
- one job is unique/overlap-protected per subscription;
- jobs timeout after 120 seconds;
- jobs try at most five times with 60/300/900/3,600-second queue backoff;
- repository failure/circuit state is bounded;
- cursor advances only after a Completed/Partial batch; and
- completed source-window replay is accepted only when the returned next cursor agrees with persisted state.

Exact source-window/candidate/promoted-record identity remains the authoritative at-least-once idempotency layer; queue uniqueness is additive rather than a substitute.

The P6 acceptance test deliberately rewinds only its test fixture cursor, replays the exact same source window, and verifies one batch, two candidates, and one canonical record per promoted target remain.

## 6. Source revocation and operational health review

`kingdoms:reconcile-ingestion-sources` runs every five minutes with single-server and overlap protection. Active/paused subscriptions are re-resolved against the current adapter registry under row locks.

If the adapter key disappears or its version no longer matches, the subscription is disabled with bounded `source_unapproved` block/failure state, and future scheduling/circuit state is cleared. Reconciliation is idempotent after disablement.

`kingdoms:ingestion-health --json` exposes aggregate, payload-free counts for active/revoked/overdue subscriptions, open circuits, stale pending candidates, quarantined candidates and recent failed batches plus an `attentionRequired` decision. It does not expose normalized payloads or source secrets and grants no business-mutation authority.

The P6 acceptance test removes adapter approval, proves one disable transition followed by an idempotent no-op, and confirms the revoked source becomes an operational attention signal.

## 7. Retention, privacy and canonical-history independence

Accepted repository-controlled default retention is:

- promoted/rejected normalized candidate payload redaction after 30 days;
- promoted/rejected candidate-row purge after 90 days;
- quarantined candidate-row purge after 180 days;
- terminal batch purge after 90 days only when no candidates remain; and
- disabled-subscription scheduling/failure compaction after 30 days while preserving the subscription identity/state row.

Retention applies to K4 operational scaffolding, not promoted K1/K3 canonical history. It never rewrites copied machine provenance and is not a replay/recovery shortcut.

The P6 acceptance test runs a shortened test-only retention schedule in two stages: payload redaction first, then terminal candidate/batch pruning. The subscription remains, while both promoted canonical records and their source provenance remain intact after K4 candidate/batch deletion.

## 8. Security, privacy and explicit non-capabilities

Whole-increment acceptance confirms K4 does not introduce:

- arbitrary manager source URLs, headers, cookies or credentials;
- source-password/API-token persistence in Kingdoms tables, audit/outbox or operational health;
- raw-response archival as normal diagnostics;
- cross-Alliance source/candidate promotion;
- name/tag/handle automatic identity matching;
- auto roster/tracking creation/reactivation;
- machine game-Alliance correction/invalidation;
- automatic transfer, diplomacy or contact mutation;
- scoring, ranking, threat/desirability assessment or recommendations;
- public Kingdoms inbound API/webhook routes; or
- scraping, OCR, browser/game-client bots or unapproved provider acquisition.

All `kingdoms.*` ingestion events remain internal/public-webhook ineligible.

## 9. Query, capacity and operational hardening review

K4-P5 added a realistic-volume operational-health gate with:

- 250 subscriptions;
- 40 recent failed batches; and
- 110 candidates.

The aggregate health snapshot remains bounded to **8 or fewer SELECT statements** at that volume. Acquisition itself remains bounded by page size, poll interval, queue concurrency, job timeout and retry/circuit policy.

This is generic repository capacity evidence, not a real-source throughput/availability SLO. Provider-specific sizing, rate limits, timeout behavior and schema/cursor guarantees remain source-enablement prerequisites.

## 10. Migration, backup and recovery review

The accepted K4 migration chain includes:

1. ingestion foundation;
2. player-snapshot machine provenance;
3. game-Alliance-observation machine provenance; and
4. ingestion scheduling/cursor/failure state.

Focused migration tests preserve dependency order and rollback/reapply behavior; full CI applies the complete PostgreSQL schema from clean state.

Shared immutable-image, ephemeral-staging and backup/restore gates remain part of acceptance. Operational recovery requires restoring runtime/database/Redis health, inspecting aggregate ingestion health, reconciling current source approval and preserving captured cursor/context rather than editing operational rows by hand.

## 11. Accessibility, interfaces and external-integration review

The manager ingestion workspace remains first-party authenticated UI under `kingdoms.manage`; privileged human changes/replay require recent password confirmation. Source-level accessibility gates cover semantic main/heading structure, native controls, labels and table overflow semantics.

No public source callback, inbound ingestion endpoint or public Kingdoms API scope exists. Operator queue/reconciliation/retention/health commands are internal runtime interfaces.

Integrations continues to reject `alliance.kingdom_updated` and all `kingdoms.*` events from generic webhook fan-out, preserving the accepted public/private boundary.

## 12. Exact protected validation evidence

Exact validated whole-increment implementation SHA:

`3e0976e8bdd32207bd6314011c26b94fa0f3c118`

Protected runs:

- Dependency Review `31556412455` — **success**;
- CodeQL `31556412413` — **success**;
- CI `31556412468` — **success**.

CI evidence:

- PHP 8.5.9;
- Composer manifest/lock validation — success;
- Composer audit — no security vulnerability advisories;
- clean PostgreSQL migrations — success through `2026_08_11_220000_add_ingestion_scheduling`;
- Pint — **529 files**;
- PHPStan/Larastan — **374/374, 0 errors**;
- ParaTest/PHPUnit — **429 tests, 9,799 assertions**;
- whole-increment `KingdomIngestionAcceptanceTest` — included and passing;
- frontend dependency audit — success;
- ESLint/Prettier/Vue-TypeScript — success;
- production frontend build — success;
- immutable production image build — success;
- ephemeral staging deployment — success;
- backup/restore demonstration — success;
- image vulnerability scan — success; and
- staging cleanup — success.

The immediately preceding P5→P6 transition head `482b3b8b3eb07bc9211ba4e7c30e1cceff6c2303` independently passed Dependency Review `31556128860`, CodeQL `31556128870`, and CI `31556128858`, proving the governed P5 Complete / P6 Current transition before P6 work began.

## 13. Residual real-source prerequisites

Repository acceptance is intentionally source-agnostic. Before any concrete production adapter can be enabled, the source-specific review must separately establish:

- provider authorization/terms and allowed automation behavior;
- endpoint ownership and transport contract;
- DNS, redirect, private/metadata-address and egress controls;
- TLS requirements;
- secret ownership/storage/rotation boundaries;
- rate limits, timeouts and retry expectations;
- stable-ID and field semantics;
- schema/version change policy;
- cursor/window guarantees;
- monitoring and provider-side revocation behavior; and
- production sizing/cutover/rollback approval.

Until that separate approval exists, production remains intentionally configured with zero ingestion adapters.

## 14. Final disposition

`KINGDOMS-004` is **Accepted** for repository/product purposes.

Accepted capability now includes the generic allowlisted ingestion control plane, existing-target factual player/game-Alliance promotion, scheduler/cursor/retry/replay, fail-closed source revocation, bounded operational retention, payload-free health monitoring and capacity evidence under the established tenant/privacy/security boundaries.

A concrete real production source, source credentials/network path, public inbound integration, cross-Alliance sharing, automated decision behavior, and production cutover remain separately **not approved** and are not implied by this acceptance.
