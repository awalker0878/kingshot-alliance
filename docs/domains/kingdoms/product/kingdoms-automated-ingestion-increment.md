# Kingdoms automated game-data ingestion product increment

[← Kingdoms product and acceptance evidence](README.md)

**Status:** Planning scope — `K4-P0` contract candidate; runtime not implemented  
**Scope ID:** `KINGDOMS-004`  
**Owning domain:** `Kingdoms`  
**Delivery model:** Post-program product increment; this is not a continuation of historical Phase numbering or the completed DCP  
**Baseline dependency:** Accepted `KINGDOMS-001`, `KINGDOMS-002`, and `KINGDOMS-003` runtime contracts  
**Implementation sequence:** [KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)  
**P0 decisions:** [KINGDOMS-004 K4-P0 design decisions](kingdoms-automated-ingestion-p0-decisions.md)

## 1. Purpose

`KINGDOMS-004` introduces controlled automated ingestion of factual Kingshot player and game-Alliance observations from explicitly approved machine-readable sources.

The increment exists to reduce repetitive manual observation entry while preserving the identity, tenancy, history, privacy, and human-decision boundaries accepted in K1–K3.

K4 is an **ingestion boundary, not an acquisition loophole**. It does not approve scraping, OCR, browser automation, Discord bots, undocumented/unapproved APIs, credential capture, arbitrary tenant-supplied URLs, or any other source merely because data could technically be collected from it.

## 2. Product outcome

For an Alliance with an explicitly enabled and approved ingestion subscription, the system can:

- acquire bounded factual records through a code-allowlisted source adapter;
- normalize source data into tenant-scoped ingestion candidates;
- preserve source/batch/capture provenance and deterministic retry identity;
- quarantine ambiguous, invalid, stale-context, or unsupported records without mutating business history;
- automatically promote only unambiguous facts for already-managed roster/tracking targets through the existing Kingdoms business actions;
- show managers what was promoted, quarantined, rejected, retried, or blocked; and
- replay safely after a transient source or processing failure without multiplying snapshots/observations.

The resulting player snapshots and game-Alliance observations remain the same Alliance-owned factual history used by K1/K3. Automated source provenance does not make those facts global or public.

## 3. Core business rules

### Approved source only

A runtime adapter must be explicitly approved before it can be enabled. Approval requires a documented source contract, authentication/secret boundary, rate/availability expectations, permitted data fields, and operational owner.

The initial K4 contract does not permit:

- arbitrary URLs or headers entered by Alliance managers;
- generic HTTP scraping;
- HTML/browser automation;
- OCR/screenshot ingestion;
- game-client automation or bots;
- undocumented/unapproved Kingshot endpoints; or
- collection of credentials, recovery material, session cookies, or unrelated private data.

### Kingdoms owns ingestion semantics

Kingdoms owns source subscription state, ingestion batches/candidates, normalization, quarantine, promotion, and the resulting game-observation provenance.

The Integrations domain continues to own the product's existing external read API and outbound webhook contracts. K4 does not turn Integrations into a generic credential vault and does not create a public Kingdoms API/webhook contract.

### Tenant and Kingdom context are explicit

Each ingestion subscription belongs to one platform Alliance and captures the Kingdom context for which the source is configured.

A scheduled/system run may act only for that subscription's Alliance. If the Alliance's current Kingdom no longer matches the captured subscription context, acquisition/promotion fails closed and the subscription is surfaced as blocked for manager review. Historical batches remain readable under tenant authorization and are never silently retargeted.

### Automated promotion is observations-only

Initial K4 automation may create only factual observation history for targets the Alliance already manages:

- `PlayerSnapshot` for an existing Alliance roster entry/neutral `KingdomPlayer`; and
- `KingdomAllianceObservation` for an existing `TrackedKingdomAlliance`/neutral `KingdomAlliance`.

K4 does **not** automatically:

- create or activate roster entries;
- start game-Alliance tracking;
- link application memberships;
- create transfer participants/groups/readiness/completion;
- change diplomacy state or contacts;
- infer player/alliance identity from display names/tags/handles;
- create recommendations, scores, rankings, or punitive decisions.

Managers may use the existing K1/K3 workflows to establish missing roster/tracking relationships and then explicitly retry/replay quarantined candidates.

### Stable game identifiers remain the only automatic identity keys

Automated records must resolve through approved stable game-player/game-Alliance identifiers scoped to the relevant Kingdom.

Names, alliance tags, handles, source row positions, and source-specific labels are not identity keys. A source-specific identifier is not automatically equivalent to a canonical game identifier unless the approved adapter contract explicitly proves that equivalence.

Ambiguous or missing identity is quarantined, never guessed.

### Existing business actions remain authoritative

Promotion must reuse the accepted K1/K3 recording behavior and invariants rather than writing directly to roster/snapshot/observation tables.

K4 may extend those actions with explicit system-source provenance, but it may not bypass:

- Alliance ownership;
- current-Kingdom validation;
- stable-ID matching;
- append-oriented history;
- exact-retry idempotency;
- correction/invalidation semantics; or
- member-safe versus manager-private field boundaries.

### At-least-once ingestion must be safe

The pipeline assumes acquisition and queue processing may retry.

Each normalized candidate therefore carries deterministic source identity derived from the Alliance subscription, adapter/source record identity, capture time, target kind, stable game identifier, and normalized factual payload. Reprocessing the same accepted source fact must resolve to the same ingestion result rather than multiplying domain observations.

A later genuinely distinct observation remains append-only history even when its factual values are unchanged.

### Quarantine before guesswork

Unsupported, malformed, ambiguous, out-of-context, future-dated, over-bound, or otherwise unsafe records are quarantined or rejected before any domain mutation.

Quarantine is not a hidden alternate datastore for business facts. Candidate data is bounded, manager-visible only where required, and retained only as long as needed for diagnosis/replay under the eventual operations policy.

### No raw secret or general raw-payload archive

Initial K4 does not persist source credentials, session tokens, cookies, authorization headers, or recovery material in Kingdoms tables, audit events, outbox payloads, logs, or candidate records.

The initial contract also does not make raw external response bodies canonical long-term storage. It stores normalized candidate fields, safe provenance, and content hashes required for integrity/idempotency. Any future raw-payload retention requires a separately reviewed bounded retention/privacy design.

## 4. In-scope capabilities

### 4.1 Code-allowlisted source adapters

Provide an adapter interface whose implementations are registered in code/configuration, not by tenant-supplied network destinations.

An approved adapter contract defines:

- source identifier and version;
- acquisition mode;
- endpoint/transport ownership;
- authentication mechanism and secret owner;
- rate and timeout bounds;
- supported record kinds/fields;
- stable-ID semantics;
- source cursor/event identity semantics; and
- operational/revocation behavior.

No concrete production source is approved merely by merging the generic K4 foundation.

### 4.2 Alliance-owned ingestion subscription

Track the approved adapter selected for one Alliance/Kingdom context with non-secret configuration, lifecycle state, scheduling/cursor metadata, and operational blocking state.

Alliance managers may enable/pause/disable only adapters already approved by repository/operator configuration.

### 4.3 Batches and normalized candidates

Persist bounded Alliance-owned acquisition attempts and normalized candidate records sufficient to diagnose and safely retry processing.

Candidate target kinds in the initial increment are exactly:

- `player_snapshot`; and
- `alliance_observation`.

The ingestion model does not contain diplomacy, transfer, scoring, recommendation, membership, or public-sharing target kinds.

### 4.4 Player snapshot promotion

For a source record containing an approved stable game-player ID that resolves to an existing roster target in the subscription's Alliance/current Kingdom, promote supported factual fields through the accepted player-snapshot action with automated-source provenance.

Missing roster targets, ambiguous identities, unsupported fields, or stale Kingdom context quarantine instead of auto-enrolling the player.

### 4.5 Game-Alliance observation promotion

For a source record containing an approved stable game-Alliance ID that resolves to an existing tracked game-Alliance target in the subscription's Alliance/current Kingdom, promote supported factual name/tag/power/member-count fields through the accepted K3 observation action with automated-source provenance.

The source never changes diplomacy state, contacts, tracking lifecycle, notes, or rankings.

### 4.6 Scheduling, retry, cursor and replay

Provide isolated background acquisition/processing with:

- bounded timeouts/rates;
- per-subscription cursor/checkpoint semantics where the approved source supports them;
- exponential/backoff or equivalent bounded retry;
- no concurrent duplicate processing of one subscription/cursor window;
- safe replay from retained normalized candidates/batches; and
- operator-visible blocked/failure state without raw secrets in diagnostics.

### 4.7 Manager review and observability

Managers can view source/subscription health, batch outcomes, promoted/quarantined/rejected counts, bounded candidate reasons, and replay results.

Application logs/metrics expose identifiers/counts/error classes/timing, not source secrets or unbounded raw payloads.

## 5. Authorization and system-actor model

- normal member-safe visibility of promoted Kingdoms facts remains `alliance.view`;
- K4 subscription configuration, quarantine review, and explicit replay require `kingdoms.manage` plus recent password confirmation;
- background workers do not impersonate a user and do not gain broad cross-tenant authorization;
- every job carries/re-resolves the owning Alliance/subscription and captured Kingdom context;
- system-produced observations record machine/source provenance distinctly from human actor provenance; and
- platform administrators do not implicitly become tenant Kingdoms managers.

No source credential, external contact, adapter identity, or scheduler assignment grants application authorization.

## 6. Data ownership

| Concept | Ownership | Tenant scope |
| --- | --- | --- |
| Adapter implementation/allowlist | Kingdoms + repository/operator configuration | Shared code/config control |
| Ingestion subscription | Kingdoms | Alliance-scoped |
| Ingestion batch | Kingdoms | Alliance-scoped |
| Normalized ingestion candidate | Kingdoms | Alliance-scoped |
| Neutral Kingdom/player/game-Alliance identity | Kingdoms | Global reference |
| Promoted player snapshot | Kingdoms | Alliance-scoped |
| Promoted game-Alliance observation | Kingdoms | Alliance-scoped |
| Audit evidence | Audit | Correlated to Alliance/system action |
| Durable ingestion event | Platform outbox | Alliance-scoped/internal |
| Existing public API/webhook state | Integrations | Unchanged; K4 not externally exposed |

## 7. Cross-domain contracts

### Alliances

Provides active Alliance and canonical current Kingdom context. K4 subscription context never becomes a replacement tenant boundary.

### Identity / Authorization

Human configuration/review uses normal authenticated authorization and recent password assurance. Background workers use explicit persisted tenant/source context, not fabricated user sessions.

### Audit / Platform

Material source configuration/replay decisions create attributable audit evidence. Durable internal K4 events use the existing transactional outbox and remain excluded from public webhook fan-out.

### Integrations

The existing read API/outbound webhook domain remains unchanged. K4 does not add write API scopes, inbound public feed endpoints, or generic external secret storage.

## 8. Delivery slices

### `K4-P0` — Source, identity, tenancy, provenance and automation contract lock

- approved-source boundary;
- adapter/subscription/batch/candidate ownership;
- no arbitrary network destinations or plaintext secrets;
- stable-ID-only automatic target resolution;
- observations-only promotion boundary;
- quarantine/idempotency/provenance contract;
- tenant/Kingdom-drift behavior; and
- internal-only event/integration boundary.

### `K4-P1` / Slice A — Ingestion foundation

- adapter registry/allowlist;
- Alliance-owned subscription lifecycle;
- batch/candidate persistence;
- normalization/quarantine framework;
- deterministic candidate identity;
- management/status surfaces; and
- no domain-observation promotion yet.

### `K4-P2` / Slice B — Player observation promotion

- approved player-record normalization;
- existing-roster/stable-player-ID resolution;
- automated-source player-snapshot provenance;
- exact-retry protection; and
- quarantine for unknown/ambiguous/out-of-context targets.

### `K4-P3` / Slice C — Game-Alliance observation promotion

- approved game-Alliance record normalization;
- existing-tracking/stable-alliance-ID resolution;
- automated-source K3 observation provenance;
- no diplomacy/contact/tracking mutation; and
- exact-retry protection.

### `K4-P4` / Slice D — Scheduling, cursor, retry and replay

- isolated queue/scheduler execution;
- source cursor/checkpoint behavior;
- timeout/rate/backoff/circuit behavior;
- concurrency guards;
- replay/reconciliation; and
- Alliance-Kingdom drift blocking.

### `K4-P5` / Slice E — Operations, review and retention hardening

- manager quarantine/batch review;
- safe reason/detail presentation;
- metrics/logging/alerts;
- bounded candidate/batch retention;
- source disable/revocation workflow; and
- recovery/operator runbook.

### `K4-P6` — Whole-increment acceptance

Whole-stack validation covers tenant isolation, stable-ID identity, source authorization, SSRF/secret boundaries, queue/retry/replay idempotency, historical integrity, rollback/reapply, query/performance bounds, accessibility, internal event exclusion, and explicit non-capabilities.

## 9. Explicitly out of scope

`KINGDOMS-004` initial scope does not implement or approve:

- scraping, OCR, browser automation, game-client automation, Discord bots, or undocumented/unapproved APIs;
- arbitrary manager-supplied source URLs/headers;
- tenant-entered source credentials/secrets;
- public inbound Kingdoms ingestion API or webhook endpoint;
- automatic roster enrollment, game-Alliance tracking, membership linking, or identity matching by name/tag/handle;
- transfer planning/readiness/completion automation;
- diplomacy state/contact/term mutation or inference;
- threat/desirability/punitive ranking, battle prediction, or automated recommendations;
- cross-Alliance/shared Kingdom intelligence (`KINGDOMS-005` remains separate); or
- real-production source enablement without a separately approved adapter/operations record.

## 10. Acceptance rule

Planning/P0 approval does not claim K4 runtime exists.

Each runtime slice must be independently reviewable/reversible, must update current Kingdoms security/operations/interfaces/testing documentation when its behavior becomes real, and must pass protected Dependency Review, CodeQL, complete CI, migration rollback/reapply where applicable, and slice-specific security tests.

Whole-increment acceptance occurs only at `K4-P6`. Real production launch/source enablement remains a separate operational approval decision.