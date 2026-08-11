# Automated game-data ingestion

[← Kingdoms domain](README.md)

**Document type:** Living capability contract  
**Status:** Current — `KINGDOMS-004` `K4-P1` / Slice A runtime validated; later promotion/scheduling slices pending  
**Owning domain:** Kingdoms

## 1. Purpose

Automated game-data ingestion provides the tenant-scoped control plane for approved machine-readable Kingshot facts without bypassing the accepted Kingdoms identity, tenancy, history, privacy, and human-decision boundaries.

`K4-P1` delivers the generic foundation only: repository/operator-allowlisted adapter definitions, Alliance/current-Kingdom subscriptions, ingestion batches, normalized candidates, quarantine/rejection state, deterministic candidate identity, and a manager status/control surface. The production adapter allowlist is intentionally empty.

## 2. Scope and non-scope

Current scope includes:

- code/configuration allowlisting through `KingdomIngestionAdapterRegistry`;
- one Alliance/current-Kingdom subscription per adapter key;
- subscription lifecycle `active`, `paused`, `disabled`;
- batch lifecycle and safe result counters;
- bounded normalized `player_snapshot` and `alliance_observation` candidates;
- quarantine when the stable game identifier is missing;
- explicit manager rejection of quarantined candidates;
- safe internal audit/outbox evidence; and
- manager-only first-party status/control UI.

Current non-scope includes any concrete production source, network acquisition, scheduler/queue worker, source credential storage, automatic snapshot/observation promotion, auto-roster/tracking creation, transfer/diplomacy mutation, scoring/ranking/recommendations, cross-Alliance sharing, or public Kingdoms API/webhook.

## 3. Model and state

`KingdomIngestionSubscription` is Alliance-owned and captures `kingdom_id`, adapter key/version, lifecycle state, safe cursor/health timestamps, and optional bounded blocked reason.

`KingdomIngestionBatch` is Alliance/subscription-owned and captures adapter/version, safe cursor/window identity, state (`pending`, `completed`, `partial`, `failed`, `blocked`), bounded counters, start/completion time, and optional stable failure code.

`KingdomIngestionCandidate` is Alliance/subscription/batch-owned and captures target kind, optional stable game ID, optional source record ID, capture time, bounded normalized payload, SHA-256 payload/identity hashes, and state (`pending`, `quarantined`, `rejected`, `promoted`). `promoted` is part of the end-to-end K4 lifecycle, but Slice A contains no action that promotes a candidate into K1/K3 observation history.

Initial target kinds are exactly `player_snapshot` and `alliance_observation`.

## 4. Invariants

1. The platform `Alliance` remains the tenant/authorization boundary.
2. Subscription/batch/candidate rows capture one Kingdom context and are never silently retargeted after Alliance-Kingdom drift.
3. Only adapter implementations explicitly registered in repository/operator configuration are selectable.
4. Production configuration currently registers no adapter.
5. No tenant-supplied URL, host, HTTP header, cookie, token, password, recovery secret, or raw external response is part of Slice A persistence.
6. Approved stable game IDs remain the only automatic identity keys; names/tags/handles/source row positions never substitute for identity.
7. Candidate normalized payload keys are allowlisted per target kind and bounded before persistence.
8. Missing stable game identity quarantines rather than guessing or creating roster/tracking state.
9. Slice A never creates `PlayerSnapshot` or `KingdomAllianceObservation` rows.
10. All `kingdoms.*` ingestion events remain internal and externally ineligible through the existing Integrations boundary.

## 5. Workflows

Managers with `kingdoms.manage` and recent password confirmation may create a subscription for an already-registered adapter, transition its state, and reject their Alliance's quarantined candidates. The management page is `/alliance/kingdom-ingestion/manage`.

Internal application services can start a batch for an active/current-Kingdom subscription, stage a normalized candidate through the registered adapter, and complete a batch with a bounded outcome/failure code. Slice A provides these domain services so later background processing has a safe contract; it does not schedule or invoke external acquisition itself.

A source-window retry returns the existing batch. A deterministic candidate retry returns the existing candidate. Rejection is tenant-scoped and does not modify K1/K3 business history.

## 6. Authorization, tenancy and privacy

The management surface and all human mutations require `kingdoms.manage`; mutation routes additionally use recent password confirmation. Submitted subscription/candidate IDs are re-resolved beneath the active Alliance.

Neutral game identity does not authorize access. Candidate/batch/subscription data is Alliance-owned operational data. The manager UI exposes bounded status/provenance only and does not serialize normalized payload bodies, source credentials, or arbitrary network configuration.

## 7. Persistence and query semantics

Slice A owns three PostgreSQL tables:

- `kingdom_ingestion_subscriptions`;
- `kingdom_ingestion_batches`; and
- `kingdom_ingestion_candidates`.

Unique constraints prevent duplicate Alliance/Kingdom/adapter subscriptions, duplicate source windows per subscription, and duplicate candidate identity per subscription. Queries are Alliance-first and expose recent bounded operational state.

Candidate identity is SHA-256 over tenant/subscription, adapter/version, target kind, source record identity, capture time, stable game ID, and normalized payload hash. This makes exact at-least-once retries deterministic without turning a later distinct capture into the same observation.

## 8. Events

Human subscription creation/state changes and candidate rejection create attributable Audit evidence plus internal Platform outbox events. Internal batch/candidate processing creates internal `kingdoms.ingestion_*` outbox evidence with bounded identifiers/state/count/hash metadata.

No source credential, raw response, arbitrary normalized payload body, or manager-private unrelated text belongs in audit/outbox metadata. Existing Integrations behavior rejects all `kingdoms.*` events from public webhook fan-out.

## 9. Failure, idempotency and concurrency

Alliance-Kingdom drift, inactive subscription state, unapproved/changed adapter version, batch-context mismatch, unsupported target kind/field, malformed value, future capture time, and missing stable identity fail closed before business-history mutation.

Subscription/batch actions use transactional row locking where state/concurrency requires it. Source-window and candidate identity constraints provide deterministic retry behavior. Completed batches cannot be rewritten to a different outcome.

Quarantine is a safe intermediate state, not permission to infer identity. Candidate replay/promotion beyond rejection remains a later K4 slice.

## 10. Operations and observability

Current runtime is first-party Laravel/PostgreSQL plus shared Audit/outbox infrastructure. **Background processing is not implemented for K4 yet**: there is no Kingdoms ingestion scheduler, source polling job, queue partition, crawler, scraper, OCR worker, bot, or concrete external provider dependency.

Operators can inspect subscription state/current-Kingdom context, recent batch status/counters, candidate state/reason, audit/outbox identifiers, and the production adapter configuration. With the current empty allowlist, the manager UI correctly reports no approved source adapters.

See [Automated ingestion operations](operations/kingdoms-automated-ingestion.md).

## 11. Tests and validation

`K4-P1` validation covers adapter allowlisting, no URL/secret persistence columns, manager/member/password boundaries, cross-tenant submitted-ID isolation, Kingdom drift, batch/candidate deterministic retries, payload field/value bounds, quarantine/rejection, no K1/K3 promotion, migration dependency-order rollback/reapply, accessibility, and K3/public-integration non-regression.

Exact validated runtime candidate: `5a37731374e9fa7aef591b7b1badd9cc13603e2c`. Protected runs: Dependency Review `31533284318`, CodeQL `31533284195`, CI `31533284398`; CI passed 509 Pint files, PHPStan/Larastan 363/363 with zero errors, 407 tests / 9,466 assertions, immutable image build, ephemeral staging, backup/restore, and image scan.

See [Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md).

## 12. Related documentation

- [KINGDOMS-004 scope](product/kingdoms-automated-ingestion-increment.md)
- [KINGDOMS-004 implementation plan](product/kingdoms-automated-ingestion-implementation-plan.md)
- [K4-P0 decisions](product/kingdoms-automated-ingestion-p0-decisions.md)
- [K4-P0 exit report](product/kingdoms-automated-ingestion-p0-exit-report.md)
- [Slice A validation](product/kingdoms-automated-ingestion-slice-a-validation.md)
- [Slice A security review](security/kingdoms-automated-ingestion-foundation-security-review.md)
- [Automated ingestion operations](operations/kingdoms-automated-ingestion.md)
- [Kingdoms interfaces](interfaces/README.md)
- [Kingdoms testing/evidence](testing/README.md)
