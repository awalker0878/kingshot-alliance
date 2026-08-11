# KINGDOMS-004 Slice A security review

[← Kingdoms security profile](README.md)

**Document type:** Living capability security review  
**Status:** Current  
**Owning domain:** Kingdoms  
**Capability:** Automated game-data ingestion foundation (`K4-P1`)  
**Code owner:** `app/Domain/Kingdoms`

## 1. Scope and security objective

This review covers the `K4-P1` generic ingestion control plane: adapter allowlisting, tenant subscriptions, batches, normalized candidates, quarantine/rejection, manager controls, and internal evidence. It does not approve a concrete networked source, source credentials, a scheduler/worker, or automated promotion into player/game-Alliance observation history.

The objective is to create the state and validation boundary needed by later automation without introducing an SSRF/credential vault, cross-tenant data path, identity guessing, or automatic player/alliance decisions.

## 2. Assets and sensitive data

Assets are Alliance/current-Kingdom subscription state, safe adapter/version identity, cursors/window identifiers, batch counts/status/failure codes, candidate stable/source IDs, capture time, bounded normalized factual payload, content/identity hashes, quarantine/rejection reason codes, and audit/outbox evidence.

Slice A does not accept source passwords/API tokens/cookies/authorization headers/recovery secrets, arbitrary endpoint URLs, or canonical raw external response bodies. Normalized candidate facts remain Alliance-owned operational data even when the underlying neutral game identity is global reference data.

## 3. Trust boundaries

Human manager → first-party management UI is authenticated, verified, active-Alliance context and `kingdoms.manage`; mutations additionally require recent password confirmation.

Repository/operator configuration → `KingdomIngestionAdapterRegistry` is the adapter approval boundary. The production allowlist is empty, so there is no current network/source trust boundary.

Internal action → PostgreSQL/Audit/outbox is the only Slice A automated processing boundary. Integrations remains a hard external boundary: all `kingdoms.*` events remain ineligible for public webhook delivery and no public inbound endpoint exists.

## 4. Threats and controls

### Arbitrary destination / SSRF

**Risk:** a manager could configure a URL that reaches metadata/private/management networks.

**Controls:** no URL/host/header fields exist in subscription/candidate persistence or manager forms; managers select only registered adapter keys; production registers none. Concrete network review is deferred to source approval.

### Secret capture or leakage

**Risk:** source credentials/raw authorization material could persist in Kingdoms tables, UI, logs, audit or outbox.

**Controls:** schema contains no credential/token/cookie/header/raw-response columns; normalized payload keys are target-specific allowlists; UI does not render normalized payload bodies; audit/outbox metadata uses bounded IDs/state/count/hash values.

### Cross-tenant submitted-ID tampering

**Risk:** one Alliance manager could mutate another Alliance's subscription/candidate.

**Controls:** controller/action queries re-resolve submitted IDs beneath active `alliance_id`; cross-tenant state requests return not found/forbidden; feature coverage proves isolation.

### Kingdom drift / silent retargeting

**Risk:** a subscription created for an old Kingdom could act on the Alliance's new Kingdom.

**Controls:** subscription captures `kingdom_id`; batch/staging/re-activation validate current Alliance Kingdom; drift blocks normal automated work; historical operational rows are retained rather than rewritten.

### Identity guessing / automatic enrollment

**Risk:** missing stable IDs could be inferred from names/tags and create business state.

**Controls:** only stable game IDs may become automatic target identity; missing stable identity quarantines; Slice A has no promotion action and tests prove zero `PlayerSnapshot`/`KingdomAllianceObservation` creation.

### Payload abuse / unbounded storage

**Risk:** a source adapter could persist arbitrary or oversized data.

**Controls:** target kind is allowlisted; normalized keys are explicit per target; strings/counts/power/capture time are bounded; unknown keys fail validation; generic raw source bodies are not retained.

### Retry multiplication / state rewriting

**Risk:** at-least-once processing could multiply batches/candidates or rewrite completed outcomes.

**Controls:** source-window and deterministic candidate uniqueness; transactional row locks; completed batch outcome is immutable except exact idempotent retry; candidate exact retry returns existing state.

### Premature decision automation

**Risk:** foundation code could introduce transfer/diplomacy/scoring behavior.

**Controls:** candidate kinds are factual observation kinds only; no transfer/diplomacy/scoring routes/models/actions are added; K3 architecture guards remain active.

## 5. Authorization, tenancy and privacy

The platform Alliance is always the tenant boundary. `kingdoms.manage` governs the management page and human actions; mutation routes require recent password assurance. Game identity, adapter identity, cursor/window values, or candidate state grant no authorization.

Manager UI disclosure is intentionally bounded to adapter/status/provenance/reason identifiers and counts. Candidate normalized payloads are not serialized to the page. No cross-Alliance aggregation exists.

## 6. Integrity, replay and concurrency

All subscription/batch/candidate rows carry Alliance and captured Kingdom context. Deterministic candidate identity includes tenant/subscription, adapter/version, target kind, source record identity, capture time, stable game ID, and normalized payload hash.

Database uniqueness plus transactional locking prevents exact-retry multiplication. Missing/invalid data never becomes zero or inferred identity. Slice A stops before business promotion, so K1/K3 append-history integrity cannot be bypassed by this slice.

## 7. Secret and data lifecycle

No source-secret lifecycle exists in Slice A because no source secret is accepted. Concrete credentials require a separately approved owner/storage/rotation/revocation design.

Batch/candidate state is operational scaffolding rather than canonical long-term game history. Exact retention/pruning is intentionally deferred to `K4-P5`; until then, operators must not treat candidate storage as a raw-data archive or manually copy source credentials/raw responses into bounded fields.

## 8. Abuse limits and failure behavior

Validation rejects unapproved adapters, unsupported target kinds/fields, malformed/bounded values, future capture time, stale Alliance-Kingdom context, inactive subscriptions, batch-context mismatch, and unsafe state transitions. Missing stable identity quarantines rather than failing open.

Slice A has no network acquisition, so request-rate/redirect/DNS/private-address/TLS/source-rate controls are not yet applicable. Those controls become mandatory before any concrete adapter approval.

## 9. Verification and evidence

Exact runtime candidate `5a37731374e9fa7aef591b7b1badd9cc13603e2c` passed Dependency Review `31533284318`, CodeQL `31533284195`, and CI `31533284398`.

CI evidence: frontend checks/build success; PostgreSQL migrations success; Pint 509 files; PHPStan/Larastan 363/363, zero errors; 407 tests / 9,466 assertions; immutable image build; ephemeral staging; backup/restore; image scan; cleanup. Tests cover allowlist/no-secret schema, authorization/password confirmation, cross-tenant IDs, drift, retry identity, bounded payload validation, quarantine/rejection, no observation promotion, accessibility, and public-webhook/API non-regression.

## 10. Residual risks and external controls

A future real source introduces network trust, authentication, terms/permission, rate/availability, source-schema integrity, and secret-management risks that Slice A deliberately cannot validate because no source is configured. `K4-P2`/`P3` must additionally prove stable-ID target resolution and safe delegated promotion; `K4-P4` must prove queue/retry/cursor/backpressure; `K4-P5` must establish retention/operational controls.

Repository validation of Slice A does not approve a concrete source, production credentials, source enablement, or real production cutover.
