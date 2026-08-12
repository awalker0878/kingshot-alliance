# KINGDOMS-004 K4-P0 security and privacy review

[← Kingdoms security profile](README.md)

**Scope ID:** `KINGDOMS-004`  
**Gate:** `K4-P0` — source, tenancy, stable-ID, provenance, quarantine and automation contract lock  
**Status:** **Candidate — no runtime impact**  
**Runtime impact:** None; this review governs later K4 implementation slices.

## 1. Review objective

This review validates the pre-runtime security model for automated game-data ingestion before source adapters, ingestion persistence, workers, schedulers, or automated observation promotion are added.

Automation increases the blast radius of bad source data and external trust mistakes. K4 therefore treats source acquisition as untrusted input, keeps tenant context explicit, and preserves the K1–K3 rule that stable game identifiers—not names/tags/handles—are the only automatic identity keys.

## 2. Protected assets

Protected or integrity-sensitive assets include:

- Alliance-owned roster/snapshot history;
- tracked game-Alliance observations/history;
- current Kingdom/tenant context;
- manager-only ingestion subscription/batch/quarantine state;
- source/provenance identifiers that could expose operational details;
- source authentication/session material handled outside Kingdoms persistence;
- audit/outbox evidence; and
- availability of request, outbox, and other critical worker capacity.

K4 normalized candidates are tenant-private operational data even when the source facts may be observable in-game.

## 3. Trust boundaries

### 3.1 External source boundary

All acquired source data is untrusted until adapter validation/normalization succeeds.

An adapter being code-allowlisted means its transport is approved for use; it does not make individual records trustworthy or exempt them from identity/value/tenant validation.

### 3.2 Active Alliance boundary

Every subscription, batch and candidate belongs to one Alliance. Workers re-resolve the owning Alliance/current Kingdom before acquisition/promotion and never use process trust as cross-tenant authorization.

### 3.3 Stable identity boundary

Source display names/tags/handles/row positions never become automatic identity keys. Automatic promotion requires the adapter-approved stable game ID and an existing Alliance-managed roster/tracking target.

### 3.4 Secret boundary

Initial K4 stores no plaintext source credential/session material in Kingdoms persistence. Adapter authentication is owned by deployment/operator configuration or another separately reviewed secret owner.

### 3.5 Public integration boundary

K4 is not a public machine contract. No public inbound ingestion endpoint, Kingdoms API scope, or webhook schema is approved. `kingdoms.*` events remain externally ineligible.

## 4. Threats and controls

### 4.1 Arbitrary URL / SSRF configuration

**Threat:** A manager configures an ingestion source to fetch cloud metadata, private management services, loopback, internal admin endpoints, or arbitrary Internet hosts.

**Controls:**

- no tenant-supplied URL/hostname/header editor;
- source adapters/endpoints are code/config allowlisted;
- each networked adapter requires its own redirect/DNS/address/TLS/timeout/egress review;
- production network policy remains a separate defense and approval item.

### 4.2 Unapproved scraping or private API acquisition

**Threat:** The generic ingestion framework becomes a justification for scraping, browser automation, bots, OCR, or undocumented/private API use.

**Controls:**

- K4 scope explicitly prohibits those acquisition methods;
- no generic HTTP/HTML/browser/OCR adapter exists in P0;
- each concrete adapter requires documented source permission/terms and owner before implementation/enablement;
- architecture tests should reject generic arbitrary-destination fetch paths introduced as K4 shortcuts.

### 4.3 Credential leakage

**Threat:** API keys, cookies, authorization headers, passwords, or recovery material enter database rows, logs, audit metadata, outbox payloads, exception messages, or manager UI.

**Controls:**

- Kingdoms subscription/candidate schemas contain no plaintext secret field;
- initial K4 does not accept tenant-entered source secrets;
- adapters consume secrets from an approved external secret/config owner;
- HTTP/client exceptions must be sanitized before persistence/logging;
- audit/outbox metadata allowlists safe identifiers/counts rather than dumping request/response objects;
- tests scan representative failures for secret/header leakage.

### 4.4 Cross-tenant subscription/batch/candidate tampering

**Threat:** A manager or worker uses another Alliance's ingestion IDs or source state.

**Controls:**

- every human query/mutation re-resolves IDs beneath the active Alliance;
- every background job carries subscription identity and re-resolves its owning Alliance;
- tenant-first persistence/index patterns support scoped access;
- cross-tenant feature tests cover subscription, batch, candidate, replay and promoted-result IDs.

### 4.5 Alliance Kingdom drift

**Threat:** An Alliance changes Kingdom while a subscription continues writing old-Kingdom facts as current.

**Controls:**

- subscription captures Kingdom context;
- acquisition and promotion revalidate current `kingdom_id`;
- drift blocks new automated work before domain mutation;
- no silent retargeting of subscription/batch/candidate/history;
- historical diagnostic reads remain tenant-authorized;
- manager explicitly reconfigures the new Kingdom context.

### 4.6 Name/tag based identity poisoning

**Threat:** Reused/changing names or tags cause source records to mutate the wrong player/game Alliance.

**Controls:**

- only adapter-approved stable game IDs participate in automatic matching;
- source-local IDs are not canonical unless the adapter review proves their semantics;
- existing roster/tracking relationship is required;
- missing/ambiguous identity quarantines;
- no name/tag/handle fuzzy matching in automated promotion.

### 4.7 Automated enrollment/tracking expansion

**Threat:** A source can silently expand the tenant's roster/tracked-intelligence set, creating surveillance/decision scope the Alliance never chose.

**Controls:**

- initial K4 promotes observations only for existing roster/tracked targets;
- no automatic roster entry creation/activation;
- no automatic tracked game-Alliance creation/activation;
- managers use existing K1/K3 workflows separately before replaying an unknown-target candidate.

### 4.8 Direct writes bypass business invariants

**Threat:** Adapter/worker code writes snapshots/observations directly, bypassing tenant checks, value bounds, idempotency, correction/history, audit/outbox, or projection rules.

**Controls:**

- promotion delegates to accepted K1/K3 recording actions;
- direct table/model mutation from K4 acquisition/normalization code is prohibited;
- architecture/source tests should enforce the promotion boundary;
- transactional behavior remains owned by the existing business actions.

### 4.9 Duplicate facts under at-least-once delivery

**Threat:** Scheduler, network, queue, or replay retries multiply observations.

**Controls:**

- deterministic candidate identity/content hash;
- unique/durable candidate retry semantics;
- promotion uses existing snapshot/observation exact-retry controls;
- cursor/checkpoint advancement occurs only at the approved success boundary;
- concurrent duplicate subscription work is durably serialized/guarded.

### 4.10 Bad source data poisons history

**Threat:** Malformed, future-dated, extreme, unsupported, or schema-shifted data is automatically accepted at scale.

**Controls:**

- adapter schema/version validation;
- bounded normalization;
- existing K1/K3 value/time validation;
- quarantine/rejection before mutation;
- source-version mismatch blocks/alerts rather than coercing data;
- promoted facts remain attributable to machine/source provenance and can use existing correction/invalidation mechanisms.

### 4.11 Raw payload creates privacy/retention risk

**Threat:** The ingestion subsystem becomes a permanent warehouse of external responses containing unnecessary or sensitive fields.

**Controls:**

- initial K4 persists normalized approved fields + safe provenance/hash, not raw response bodies;
- unsupported/unapproved fields are discarded during normalization rather than copied forward;
- any future raw-payload retention requires separate privacy/storage/retention review;
- batch/candidate operational state receives bounded retention in Slice E.

### 4.12 Queue/resource exhaustion

**Threat:** Slow/rate-limited external sources consume worker/database capacity needed for requests, notifications, integrations, or outbox processing.

**Controls:**

- isolated K4 worker/queue partition;
- bounded source timeouts/rates and batch sizes;
- bounded retry/backoff/circuit behavior;
- per-subscription concurrency control;
- capacity/latency metrics and alerting;
- K4 failures do not block critical queues.

### 4.13 Replay abuse

**Threat:** A manager or operator repeatedly replays large batches, multiplying load or bypassing normal source limits.

**Controls:**

- replay requires `kingdoms.manage` + recent password confirmation;
- replay uses existing retained candidate/batch identity and remains idempotent;
- bounded batch/replay size and rate;
- attributable audit evidence for explicit replay/reject/configuration actions;
- no replay path can alter the source identity tuple to fabricate a new observation.

### 4.14 Automation crosses into decisions

**Threat:** Ingested power/member/player data silently changes diplomacy, transfer readiness, membership, ranking, threat scoring, or recommendations.

**Controls:**

- initial target kinds are exactly player snapshot and game-Alliance observation;
- no transfer/diplomacy/membership/scoring candidate types;
- K3 diplomacy remains explicit human state;
- K2 transfer readiness/completion remains explicit human workflow;
- tests prove representative ingestion does not mutate those aggregates.

### 4.15 Internal ingestion events become public

**Threat:** K4 event types leak through wildcard webhook subscriptions.

**Controls:**

- all K4 events remain under `kingdoms.*`;
- existing Integrations Kingdoms exclusion remains authoritative;
- representative K4 event names are added to exclusion regression tests before runtime acceptance;
- event payloads exclude raw candidate/source secret/private text.

## 5. Authorization matrix

| Action | Required authority |
| --- | --- |
| View ordinary promoted roster/alliance facts | `alliance.view` through existing K1/K3 presentation |
| View/manage ingestion subscription/batches/quarantine | `kingdoms.manage` |
| Enable/pause/disable subscription | `kingdoms.manage` + recent password confirmation |
| Explicit replay/reject quarantined candidate | `kingdoms.manage` + recent password confirmation |
| Background acquisition/promotion | System worker bound to persisted subscription + Alliance/Kingdom re-resolution; no User impersonation |
| Approve/enable concrete production adapter | Repository/operator source approval outside ordinary tenant authorization |

Platform administrator status does not implicitly confer tenant K4 management rights.

## 6. Privacy and data minimization

Initial K4 collects only fields already approved for K1 player snapshots or K3 game-Alliance factual observations plus safe ingestion provenance required for integrity/replay.

It does not expand the product into collection of:

- private communications;
- credentials/session material;
- phone/address/contact-directory data;
- chat content;
- behavioral profiling beyond accepted factual observations; or
- cross-tenant intelligence.

Game visibility does not erase tenant privacy: a tenant's chosen source subscriptions, candidates, quarantine history and promoted observation provenance remain Alliance-owned.

## 7. Logging, audit and outbox rules

Safe diagnostics may include:

- Alliance/subscription/batch/candidate IDs;
- adapter key/version;
- bounded source record/cursor identifiers when approved;
- target kind;
- result/error class;
- counts/timing/retry number; and
- safe content hash.

Do not log/persist:

- credentials/cookies/authorization headers;
- raw response/request bodies;
- full external client exception dumps containing headers;
- unrelated source fields; or
- manager-private K1–K3 narrative.

Audit/outbox events must use explicit metadata allowlists.

## 8. Failure and recovery security

Source failure must fail closed without weakening tenant or validation rules.

Recovery uses bounded retry or explicit replay from retained normalized state. Operators/managers do not fix failures by direct database edits, cursor fabrication, disabling stable-ID validation, or bypassing quarantine.

If a source is compromised/deprecated or its schema/identity semantics become uncertain, disable the adapter/subscriptions before further acquisition. Existing promoted observations remain historical facts with source provenance and can be corrected/invalidated through the owning K1/K3 workflows.

## 9. Production source approval dependency

Repository acceptance of generic K4 code is insufficient to enable a real source.

A production adapter requires non-secret evidence for:

- source permission/terms and owner;
- endpoint/transport trust boundary;
- exact adapter/version;
- authentication/secret owner;
- permitted fields and stable-ID semantics;
- timeouts/rates/cursors;
- egress/SSRF/network controls where networked;
- schema-change/revocation process; and
- production monitoring/support ownership.

No source is considered approved by implication.

## 10. P0 security exit criteria

K4-P0 security is complete only when:

- the product scope, P0 decisions and implementation plan match this review;
- K4 remains planning/no-runtime in current capability navigation;
- no existing K1–K3 living contract falsely claims automated ingestion is active;
- no arbitrary source/secret/public-ingress path is introduced;
- protected documentation/architecture checks pass on the exact P0 candidate head; and
- final P0 evidence/status also passes its exact-head gate.

P0 acceptance authorizes Slice A foundation only; it does not approve a concrete production adapter.