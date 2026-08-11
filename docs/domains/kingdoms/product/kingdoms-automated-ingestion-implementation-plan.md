# KINGDOMS-004 implementation plan

[← Kingdoms automated game-data ingestion product increment](kingdoms-automated-ingestion-increment.md)

**Status:** Planning — `K4-P0` contract candidate; no K4 runtime implemented  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Baseline:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` implementations  
**K4-P0 decisions:** [KINGDOMS-004 K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)  
**Important:** These are implementation phases inside `KINGDOMS-004`; they are not historical product phases and are not DCP phases.

## 1. Purpose

This plan sequences controlled automated game-data ingestion into independently reviewable slices while preserving the accepted Kingdoms identity, tenancy, append-history, privacy, authorization, and non-automation boundaries.

K4 automates acquisition and factual observation recording only. It does not automate roster membership, transfer decisions, diplomacy, scoring, recommendations, or external sharing.

The implementation must preserve:

- explicit Alliance tenancy and captured Kingdom context;
- stable game identifiers as the only automatic identity keys;
- existing roster/tracking relationships as prerequisites for automatic promotion;
- append-oriented player/game-Alliance observation history;
- deterministic exact-retry behavior;
- existing K1/K3 recording actions as the only promotion path;
- manager-private versus member-safe data boundaries;
- code-allowlisted sources rather than arbitrary network destinations;
- no source secrets/raw authorization material in Kingdoms persistence/logs/events;
- internal-only `kingdoms.*` outbox events;
- reversible migrations and slice-level validation; and
- production source enablement separate from repository acceptance.

## 2. Phase status

| Phase | Status | Outcome | Delivery slice |
| --- | --- | --- | --- |
| `K4-P0` | Candidate | Source, tenancy, stable-ID, provenance, quarantine, secret and automation contracts locked | Pre-runtime contract gate |
| `K4-P1` | Blocked by P0 | Source registry, Alliance subscription, batch/candidate and quarantine foundation | Slice A |
| `K4-P2` | Blocked by P1 | Player observation promotion through accepted snapshot behavior | Slice B |
| `K4-P3` | Blocked by P2 | Game-Alliance observation promotion through accepted K3 behavior | Slice C |
| `K4-P4` | Blocked by P3 | Scheduler/queue/cursor/retry/replay and backpressure | Slice D |
| `K4-P5` | Blocked by P4 | Manager review, observability, retention and operational hardening | Slice E |
| `K4-P6` | Blocked by P5 | Whole-increment security/accessibility/rollback/performance/operations acceptance | Whole increment |

No phase may be marked complete because a later phase intends to repair it.

## 3. `K4-P0` — Design and security contract lock

### Objective

Lock the source/acquisition boundary and the rules that prevent automation from bypassing K1–K3 business invariants.

### Required decisions

P0 must lock:

- the distinction between an approved adapter and an arbitrary external URL;
- Alliance-owned subscription/batch/candidate tenancy;
- captured Kingdom context and drift failure behavior;
- normalized candidate target kinds exactly `player_snapshot` and `alliance_observation` for initial K4;
- stable game IDs as the only automatic target-resolution keys;
- existing roster/tracking relationship prerequisites;
- no automatic roster/tracking/membership/transfer/diplomacy creation;
- no generic raw-payload archive;
- no source credentials/session material in Kingdoms state/logs/audit/outbox;
- system-source provenance distinct from human actor provenance;
- candidate/batch idempotency and replay semantics;
- quarantine/rejection before ambiguous mutation;
- internal-only K4 event families; and
- no public inbound K4 API/webhook.

### Exit gate

P0 is complete only when the product scope, P0 decisions, implementation plan, and P0 security/privacy review agree with no contradictory source/identity/privacy semantics and protected validation passes on the exact evidence head.

P0 completion authorizes Slice A only; it does not approve a concrete production source or claim automated observations are running.

## 4. `K4-P1` / Slice A — Ingestion foundation

### Objective

Create the generic tenant-scoped ingestion control plane without promoting source data into K1/K3 observations yet.

### Planned domain model

The exact class/table names may be normalized during implementation, but Slice A must provide concepts equivalent to:

- `KingdomIngestionSubscription` — one Alliance/current-Kingdom binding to one approved adapter key;
- `KingdomIngestionBatch` — one acquisition/processing attempt or source cursor window; and
- `KingdomIngestionCandidate` — one bounded normalized factual record pending/quarantined/rejected/promoted state.

Adapter implementations are code/config allowlisted and are not arbitrary persisted URLs.

### Subscription contract

Persist only non-secret operational configuration required by the approved adapter, including:

- Alliance ID;
- captured Kingdom ID;
- adapter key/version;
- active/paused/disabled lifecycle;
- safe schedule/config parameters within adapter-defined bounds;
- source cursor/checkpoint where applicable;
- last success/failure/blocked timestamps; and
- timestamps/audit correlation.

Do not store plaintext credentials, session cookies, authorization headers, or arbitrary endpoint URLs.

### Batch/candidate contract

A batch records bounded safe provenance such as adapter, source cursor/window identity, acquisition/processing times, result counts/status, and safe error class.

A candidate records only the normalized fields required to validate/promote one supported observation plus deterministic source identity/checksum. Initial target kinds are exactly `player_snapshot` and `alliance_observation`.

No transfer/diplomacy/scoring/public-sharing candidate type exists.

### Management surfaces

Managers with `kingdoms.manage` + recent password confirmation may:

- enable/pause/disable an already-approved adapter subscription;
- view safe subscription/batch/candidate state;
- reject a quarantined candidate; and
- request safe replay where the source/business context is now valid.

No generic URL/secret editor is introduced.

### Tests and exit criteria

- tenant ownership and cross-tenant ID tampering;
- current-Kingdom capture/drift blocking;
- adapter allowlist enforcement;
- arbitrary URL/header/secret rejection;
- candidate target-kind allowlist;
- deterministic duplicate candidate identity;
- bounded normalized payload validation;
- audit/outbox secret-safety;
- migration rollback/reapply; and
- explicit proof that no player/alliance observations are promoted in Slice A.

## 5. `K4-P2` / Slice B — Player observation promotion

### Objective

Promote approved player facts into existing Alliance-owned snapshot history without automatically changing roster membership.

### Resolution rules

A player candidate may promote only when:

1. subscription Alliance/current-Kingdom context is valid;
2. the candidate carries the approved stable game-player ID;
3. that ID resolves to the neutral player behind an existing Alliance roster entry; and
4. all supported factual fields/capture time pass the accepted snapshot validation rules.

Unknown or ambiguous targets quarantine. Name-only matching is prohibited.

### Promotion behavior

Promotion delegates to the accepted K1 snapshot recording action, extended only as required to represent machine/source provenance distinctly from a human actor/import.

It must not:

- create a roster entry;
- change roster status;
- link/unlink membership;
- create transfer state;
- overwrite prior snapshots; or
- convert missing values to zero.

### Idempotency

The K4 candidate source identity plus the accepted snapshot idempotency contract must make replay safe across acquisition retries, queue retries, and explicit manager replay.

### Tests and exit criteria

Include stable-ID resolution, unknown roster quarantine, tenant/Kingdom drift, exact retry, genuinely later identical-value observation, field bounds, source provenance, member-safe serialization, and direct-table-write architecture protection.

## 6. `K4-P3` / Slice C — Game-Alliance observation promotion

### Objective

Promote approved game-Alliance facts into existing K3 tracked-alliance observation history without automating tracking or diplomacy.

### Resolution rules

A game-Alliance candidate may promote only when:

1. subscription Alliance/current-Kingdom context is valid;
2. the candidate carries the approved stable game-Alliance ID;
3. that ID resolves to the neutral game Alliance behind an existing active tracked relationship for the same Alliance; and
4. supported factual fields/capture time pass K3 observation validation.

Unknown/untracked/ambiguous targets quarantine. Name/tag matching is prohibited.

### Promotion behavior

Promotion delegates to the accepted K3 observation action with machine/source provenance.

It must not:

- create/activate tracking;
- change manager notes;
- change diplomacy state/history/terms;
- create/deactivate contacts;
- rank or score alliances; or
- trigger transfer workflow changes.

### Tests and exit criteria

Include stable-ID target resolution, untracked target quarantine, tenant/Kingdom drift, exact retry, invalidation/correction compatibility, latest projection behavior, diplomacy non-mutation, contact privacy, and public API/webhook exclusion.

## 7. `K4-P4` / Slice D — Scheduling, cursor, retry and replay

### Objective

Make approved ingestion reliable under external/source failure without multiplying domain facts or blocking critical application work.

### Runtime rules

- external acquisition runs only in background processing;
- use an isolated queue/worker partition sized separately from critical request/outbox work;
- each adapter declares bounded connect/read/total timeouts and rate limits;
- source cursor/checkpoint advances only after the accepted adapter-specific success boundary;
- overlapping work for the same subscription/cursor window is prevented with durable concurrency control;
- transient failures use bounded retry/backoff;
- persistent failures block/alert rather than retry forever;
- Alliance-Kingdom drift blocks acquisition/promotion before mutation;
- replay consumes retained normalized candidates/batch identity and remains idempotent.

### Failure boundaries

Source outage, authentication failure, malformed response, rate limit, cursor conflict, normalization error, quarantine, promotion failure, and outbox failure must be distinguishable in diagnostics without logging secrets/raw payloads.

### Tests and exit criteria

Include duplicate scheduled run/concurrency tests, retry exhaustion, cursor no-advance on failure, replay after transient failure, queue isolation, drift blocking, and worker restart safety.

## 8. `K4-P5` / Slice E — Operations, review and retention hardening

### Objective

Make ingestion safely operable and reviewable without turning quarantine into a permanent raw-data warehouse.

### Manager workflow

Provide bounded filtered views for:

- subscription health/block state;
- recent batches;
- promoted/quarantined/rejected counts;
- safe candidate validation reasons; and
- explicit replay/reject actions.

### Observability

Metrics/logs should cover acquisition duration/result, records received/normalized/promoted/quarantined/rejected, retry/circuit state, cursor lag where meaningful, and promotion latency.

Logs must not include credentials, cookies, authorization headers, arbitrary raw response bodies, or manager-private unrelated data.

### Retention

Define and test bounded retention for non-canonical batch/candidate operational state. Promoted K1/K3 observations retain their existing domain history rules; deleting expired K4 operational scaffolding must not delete promoted facts.

### Operational runbook

Add Kingdoms-owned guidance for source enable/disable, diagnosing blocked subscriptions, rotating/revoking source authentication through its approved owner, replaying safely, handling source schema/version changes, and disabling a compromised/deprecated adapter.

## 9. `K4-P6` — Whole-increment acceptance

### Whole-stack validation

Acceptance must cover:

- all K4 migrations from K3 baseline and rollback/reapply back to the exact K3 schema;
- 14-domain architecture/documentation maintenance gates;
- tenant isolation and submitted-ID tampering;
- source allowlist/no arbitrary SSRF destination;
- credential/log/audit/outbox secret leakage;
- stable-ID-only automatic target matching;
- unknown/untracked target quarantine;
- append/history integrity and exact retry under at-least-once delivery;
- scheduler/queue/cursor/retry/replay behavior;
- source schema/version failure handling;
- manager UI accessibility;
- query-count/capacity tests at realistic batch/subscription volumes;
- internal `kingdoms.*` event exclusion from public webhooks;
- no public Kingdoms API/inbound ingestion endpoint;
- no automated transfer/diplomacy/scoring/recommendation behavior; and
- immutable image/staging/backup/restore/scan evidence.

### Acceptance output

K4 acceptance requires:

- whole-increment security review;
- operations/runbook evidence;
- accessibility validation;
- exact validated implementation SHA and protected workflow run IDs;
- `KINGDOMS-004` exit report; and
- updates to the living Kingdoms domain/security/operations/interfaces/testing/capability docs to reflect only implemented behavior.

Repository acceptance still does not approve real production source credentials/endpoints or production cutover.

## 10. Source approval gate

Generic K4 framework acceptance and concrete adapter approval are separate.

Before any real source adapter is enabled in production, record:

- source/transport owner;
- documented permission/terms basis;
- exact adapter/version;
- approved data fields/stable-ID semantics;
- endpoint/network trust boundary;
- authentication/secret owner;
- rate/timeout/cursor expectations;
- revocation/schema-change procedure; and
- non-secret production evidence identifier.

Without that record, the adapter remains disabled even if generic K4 code is repository-accepted.