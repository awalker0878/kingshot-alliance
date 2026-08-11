# KINGDOMS-004 K4-P0 design decisions

[← KINGDOMS-004 implementation plan](kingdoms-automated-ingestion-implementation-plan.md)

**Scope ID:** `KINGDOMS-004`  
**Gate:** `K4-P0` — source, tenancy, stable-ID, provenance, quarantine and automation contract lock  
**Status:** **Candidate — no runtime impact**  
**Runtime impact:** None. This record authorizes later sliced implementation only after protected P0 validation.

## 1. Purpose

`K4-P0` locks the business/security contract for automated game-data ingestion before source configuration, ingestion persistence, workers, schedulers, or observation promotion are introduced.

K4 must reduce repetitive manual data entry without turning automation into a path around K1–K3 identity, tenancy, privacy, history, or human-decision controls.

## 2. Approved-source model

### 2.1 Adapter allowlist

A K4 source is represented by a code/configuration-registered adapter key and version.

Alliance managers do not enter arbitrary source URLs, hostnames, HTTP methods, headers, cookies, or authentication material. An adapter implementation defines its network/transport boundary and is unavailable unless enabled by repository/operator configuration.

### 2.2 No acquisition loophole

K4 initial scope does not authorize:

- generic web scraping;
- HTML/browser automation;
- OCR/screenshot ingestion;
- game-client automation;
- Discord or other bots acting as data collectors;
- undocumented/unapproved Kingshot APIs;
- credential/session-cookie harvesting; or
- reverse-engineered/private endpoints lacking explicit approval.

A future source can be added only through an explicit adapter/source review that documents permission, endpoint/transport, fields, identity semantics, authentication, rate/timeout behavior, ownership, and revocation.

### 2.3 No concrete production source is approved by P0

P0 approves the architecture for controlled ingestion, not a real production feed.

A concrete adapter may be implemented in a later slice only when its source contract is reviewed in the same change. Production enablement remains an operations/approval decision even after repository acceptance.

## 3. Ownership model

### 3.1 Kingdoms owns ingestion semantics

Kingdoms owns:

- source adapter contract/allowlist semantics;
- Alliance ingestion subscriptions;
- ingestion batches;
- normalized ingestion candidates;
- quarantine/rejection/promotion/replay state;
- machine/source provenance attached to promoted Kingdoms observations; and
- K4-specific management/query behavior.

### 3.2 Existing domains retain their ownership

- Alliances owns active tenant/current-Kingdom context.
- Identity/Authorization own human assurance/permission evaluation.
- Audit owns audit evidence.
- Platform owns shared scheduler/queue/outbox/runtime infrastructure.
- Integrations retains the current public read-API/outbound-webhook boundary.

K4 does not introduce a generic Integrations credential vault, a public inbound Kingdoms API, or external Kingdoms webhook schemas.

## 4. Canonical ingestion concepts

The implementation may normalize exact class/table names, but it must preserve these concepts.

### 4.1 Adapter

A code-allowlisted implementation identified by stable adapter key/version. It defines supported record kinds, source identity/cursor semantics, safe configuration, acquisition/authentication ownership, and normalization behavior.

### 4.2 Subscription

An Alliance-owned relationship to one approved adapter and one captured Kingdom context.

Initial lifecycle vocabulary is exactly:

- `active`
- `paused`
- `disabled`

A subscription stores no plaintext source secret or arbitrary endpoint URL.

### 4.3 Batch

An Alliance-owned acquisition/processing attempt or source cursor window.

Batch state must distinguish at minimum:

- accepted acquisition/processing;
- partial completion with quarantined records;
- terminal/transient failure; and
- blocked tenant/Kingdom/source state.

Exact persisted enum names may be normalized in Slice A, but diagnostics cannot collapse these outcomes into one generic failure state.

### 4.4 Candidate

A bounded normalized record representing one potential factual observation.

Initial target kinds are exactly:

- `player_snapshot`
- `alliance_observation`

A candidate can be promoted only once materially. Retry/replay must resolve the existing promotion result.

Candidate outcomes must distinguish promoted, quarantined and rejected records. A candidate may remain pending only while actively queued/processing; it is not an indefinite holding state.

## 5. Tenant and Kingdom context

Every subscription, batch and candidate is Alliance-owned.

The subscription captures the Alliance's Kingdom context at configuration time. Every acquisition/promotion run re-resolves the Alliance and verifies its current `kingdom_id` still matches the captured context.

If the Alliance changes Kingdom:

- historical batches/candidates remain tenant-readable under normal authorization;
- new acquisition/promotion against the stale subscription fails closed;
- no batch/candidate/observation is silently retargeted to the new Kingdom;
- the manager must disable/reconfigure/create a deliberate subscription for the new context; and
- safe diagnostic state may show the subscription as blocked without mutating unrelated business history.

## 6. Automatic target resolution

### 6.1 Player observations

Automatic promotion requires an approved stable game-player ID that resolves to the neutral player behind an existing Alliance roster entry in the same current Kingdom.

K4 does not auto-create/activate a roster entry and does not match by display name.

### 6.2 Game-Alliance observations

Automatic promotion requires an approved stable game-Alliance ID that resolves to the neutral game Alliance behind an existing active `TrackedKingdomAlliance` for the same Alliance/current Kingdom.

K4 does not auto-start tracking and does not match by name/tag.

### 6.3 Source IDs are not identity by default

A source-specific record/account ID is not automatically a canonical game ID. The adapter contract must explicitly establish that the supplied field is the approved stable game identifier before it participates in automatic matching.

Missing, malformed or ambiguous identity is quarantined rather than guessed.

## 7. Promotion boundary

### 7.1 Existing business actions are authoritative

K4 promotion must delegate to the accepted K1/K3 business recording behavior.

Direct writes from adapter/worker code to player-snapshot or game-Alliance-observation tables are prohibited.

The existing actions may be extended with explicit automated-source provenance, but the same validation/tenant/history invariants remain authoritative.

### 7.2 Initial K4 is observations-only

Automated ingestion never directly mutates:

- roster membership/status/linkage;
- transfer plans/participants/groups/readiness/blockers/completion;
- game-Alliance tracking lifecycle/manager notes;
- diplomacy state/history/terms;
- diplomacy contacts;
- application membership/roles/permissions; or
- ranking/recommendation/decision state.

A manager may use existing first-party workflows separately, then replay an otherwise valid quarantined candidate.

## 8. Provenance and idempotency

### 8.1 Machine provenance

Promoted observations record machine/source provenance distinctly from human actor/import provenance.

The source context must be explainable through safe identifiers such as subscription, adapter/version, batch, source record/event identity, capture time and normalized content hash.

No fake `User` actor is created for background ingestion.

### 8.2 Deterministic candidate identity

Exact source retries must not multiply candidates or observations.

The candidate idempotency identity is derived from a stable tuple equivalent to:

- Alliance/subscription identity;
- adapter key/version;
- target kind;
- source record/event identity when supplied;
- captured/source effective time;
- canonical stable game target ID; and
- normalized factual payload hash.

The exact hash/index representation is a Slice A implementation detail.

### 8.3 Later identical values remain history

A later source observation with a genuinely later capture/effective time remains a new candidate/observation even if its factual values are unchanged.

## 9. Quarantine and rejection

A candidate must not promote when it has:

- missing/ambiguous stable identity;
- no existing required roster/tracking target;
- stale Alliance-Kingdom context;
- unsupported record kind/field;
- invalid or out-of-bound factual values;
- unacceptable/future capture time under the adapter/source contract;
- source-version/schema mismatch;
- unsafe/unexpected payload shape; or
- another business validation failure.

Quarantine is recoverable through explicit review/replay when the condition can safely change. Rejection is used when the record is unsupported or should not be retried.

Neither state permits a hidden direct mutation path.

## 10. Payload and secret handling

### 10.1 No secret persistence in Kingdoms

Kingdoms persistence, candidates, audit events, outbox messages and ordinary logs must not contain:

- API keys/tokens;
- cookies/session material;
- authorization headers;
- passwords/recovery material; or
- unbounded external response bodies.

The approved adapter obtains authentication through deployment/operator-managed configuration or another separately reviewed secret owner.

Initial K4 does not add tenant-entered source credentials.

### 10.2 No canonical raw-response warehouse

Initial K4 persists normalized candidate fields and safe provenance/content hashes only.

Raw source response bodies are not canonical domain data and are not retained by default. A future need for raw retention requires a separately reviewed privacy/retention/storage design.

## 11. Scheduler and background authority

Background processing may execute only subscriptions already persisted/enabled for one Alliance and an adapter enabled by operator/repository configuration.

Workers:

- carry subscription/Alliance identity explicitly;
- re-resolve current tenant/Kingdom context before acquisition/promotion;
- do not impersonate a User;
- cannot enumerate/mutate another Alliance merely because the worker process is trusted; and
- use transactions/locking/advisory locks or equivalent durable guards where concurrent duplicate work could violate cursor/idempotency semantics.

Human configuration/replay remains `kingdoms.manage` plus recent password confirmation.

## 12. Network and SSRF boundary

The initial K4 manager UI does not accept arbitrary network destinations.

Adapters use code/config allowlisted endpoint/transport definitions. Redirect behavior, DNS/private/metadata/management address controls, TLS requirements, timeout/rate limits, and production egress controls are adapter/security-review responsibilities before a networked adapter can be approved.

K4 generic foundation cannot claim production SSRF resistance merely because no arbitrary URL is stored; the concrete adapter review must still validate its outbound network behavior.

## 13. Events, audit and public integration boundary

Material K4 management/promotion outcomes create attributable audit/internal outbox evidence where required.

Planned event concepts may include:

- `kingdoms.ingestion_subscription_changed`
- `kingdoms.ingestion_batch_completed`
- `kingdoms.ingestion_candidate_promoted`
- `kingdoms.ingestion_candidate_quarantined`
- `kingdoms.ingestion_replay_requested`

Exact names are implementation details, but all remain `kingdoms.*` internal-only events.

Private/raw source content and secret material never enter audit/outbox metadata.

K4 creates no public API scope, inbound ingestion endpoint, webhook schema, or generic wildcard eligibility.

## 14. Retention and deletion principles

Promoted snapshots/observations follow their existing K1/K3 history semantics.

Batch/candidate state is operational scaffolding rather than permanent duplicate business history. Slice E must define bounded retention that allows diagnosis/replay without retaining unnecessary source material indefinitely.

Expiring K4 operational state must not delete promoted observations or rewrite historical source provenance required by those observations.

## 15. Explicit non-capabilities

K4-P0 does not approve or reserve runtime placeholders for:

- automated roster enrollment/tracking creation;
- player/alliance identity inference by names/tags/handles;
- diplomacy inference/transitions;
- transfer automation;
- cross-Alliance/shared intelligence (`KINGDOMS-005`);
- threat/desirability/risk/punitive scoring;
- battle prediction;
- automated recommendations/negotiation/player management;
- public Kingdoms API/webhooks/inbound feed endpoints;
- arbitrary manager-configured network fetches; or
- scraping/OCR/browser/game-client/bot acquisition.

## 16. P0 exit decision

`K4-P0` is complete only when:

- this decision record, the increment scope, implementation plan and P0 security/privacy review agree;
- navigation clearly labels K4 as planning/no-runtime;
- no existing living runtime contract is rewritten to imply K4 exists;
- repository documentation/architecture checks remain green on the exact candidate head; and
- the final P0 evidence/status head also passes protected validation.

P0 acceptance authorizes **Slice A only**. It does not approve any concrete production data source or production cutover.