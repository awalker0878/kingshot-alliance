# Gift Code research-backed source acquisition plan

Baseline: `main` at `b7dd632f51d9e3fe63bf9bbbae25c7a71e1f44c5`.

This implementation treats the deployment as fresh: base schema changes are made directly, with no migration compatibility shims or dual-write transition paths.

## Target architecture

Gift Code acquisition is hybrid:

- push for lowest-latency official discovery where the provider offers a legitimate event interface;
- head polling for freshness fallback;
- reconciliation polling for completeness proof;
- independent historical backfill state;
- manual/registered evidence for sources without a legitimate machine interface;
- one canonical ingestion, provenance, trust, fact-reconciliation and notification pipeline for all transports.

Push is an acquisition optimization, not a trust grant. Pull reconciliation remains independent so a missed, delayed, expired or disabled push subscription can be detected instead of silently losing evidence.

Application command definitions for this capability belong in `routes/console.php`, following the repository's console-registration pattern; this work does not add capability-specific classes under `app/Console/Commands`.

## Delivery order and status

1. **Implemented:** replace the single ingestion cursor with explicit per-source synchronization state for head, incremental, reconciliation and backfill modes.
2. **Implemented:** correct partial-quarantine health so completed-with-quarantine sources surface as degraded and retain evidence-quality diagnostics.
3. **Implemented behind permission gate:** productionize the Century Games source as a richer first-party adapter that can extract explicit availability and expiry evidence from Kingshot publication content.
4. **Implemented:** shared conservative Gift Code evidence extraction for explicit code labels, expiry, applicability and reward facts.
5. **In closeout:** expand real-publication and false-positive fixture coverage.
6. **Implemented:** first-class durable push delivery/subscription infrastructure feeding the canonical observation ingestion path.
7. **Implemented:** YouTube WebSub push-first discovery with Data API canonical fetch, reconciliation and backfill.
8. **Implemented:** X incremental pagination/high-water atomicity and entitlement-gated Filtered Stream webhook transport; timeline polling remains reconciliation/fallback.
9. **Implemented:** Discord Gateway push-first discovery with REST canonical-message retrieval and reconciliation.
10. **Implemented where Meta permissions allow:** Facebook Page webhook discovery with signed delivery validation and canonical Graph fetch; Graph polling remains reconciliation/backfill.
11. **Intentionally poll-first:** Instagram until Meta exposes and the application is approved for a documented own-media publication event suitable for this source.
12. **Intentionally independent/discovery-only:** Reddit, with separate freshness/backfill state and no automatic verification.
13. **Implemented for structured feeds:** conditional HTTP retrieval using ETag/Last-Modified state, including 304 idle semantics.
14. **In closeout:** require every pull adapter to pass a shared end-to-end conformance contract.
15. **In closeout:** require every push transport to pass authentication, replay, idempotency, crash-recovery and reconciliation-gap tests.
16. **In closeout:** complete the provider failure matrix and parser-drift fixture tests.
17. **Implemented:** useful-evidence, quarantine, subscription and reconciliation-gap health metrics.
18. **Partially implemented:** operator alerting and source controls are present; live non-ingesting provider smoke checks remain to be completed.
19. **Operational rollout requirement:** enable push transports in shadow/canary mode while retaining reconciliation.
20. **In closeout:** reconcile product/reference/operations documentation and the delivery ledger after CI and conformance tests are green.

## Authority boundaries

Research catalogue membership never grants source authority. Official-source automatic verification requires configured source identity, current activation readiness, legitimate provider access, successful transport authentication where applicable, canonical source validation and an explicit evidence grammar match. Independent sources remain corroboration/discovery unless an explicit source policy grants otherwise.

`authority_promotion_enabled` is a separate operator control from acquisition. Enabling polling, push, reconciliation or backfill must never implicitly increase the source's authority.

## Provider-specific boundaries

### Century Games

The public Century Games RSS endpoint demonstrates a machine-readable publishing surface, but its existence does **not** establish permission to ingest it. Century Games' published terms prohibit scraping/reproduction without express permission. The existing `provider_permission_confirmed` activation gate therefore remains authoritative.

Until express permission or a cooperative feed/webhook contract is actually confirmed, the Century Games adapter must remain ingestion-disabled. If permission is granted, the approved feed path can emit explicit availability and expiry evidence with canonical publication links and conditional retrieval.

### X

The preferred real-time path is Filtered Stream webhook delivery scoped to the confirmed official account. That path is enabled only when the configured X account has the required webhook/stream entitlement and secrets. The normal X user-post timeline adapter remains the reconciliation and fallback path, with high-water advancement committed only after the bounded incremental page set has been safely drained.

### YouTube

WebSub is the preferred discovery path. A WebSub event is treated as a signal to retrieve canonical video metadata through the YouTube Data API before evidence extraction. REST polling remains reconciliation and historical backfill rather than the primary freshness cursor.

### Discord

A legitimately installed bot may use Gateway `MESSAGE_CREATE` events for low-latency discovery only within the approved guild/channel/author scope and with required message-content access. Canonical REST message retrieval and high-water reconciliation remain available for reconnect and completeness recovery. Self-bots and user-token automation are excluded.

### Meta

Facebook Page push is supported only for an application with the required Page access, webhook configuration and App Review/permission state. Signed webhook events identify candidate Page posts; the application retrieves canonical Graph content before extracting evidence. Instagram remains polling-only until an equally appropriate documented publication event is verified and approved for the configured professional account.

### Reddit

Reddit remains optional independent discovery. It never auto-verifies Gift Codes and failure or future Data API/Developer Platform transition must not degrade the core official-source capability.

## Definition of complete

The source acquisition subsystem is complete when:

- freshness state is independent of historical paging;
- no high-water mark can move past unprocessed provider content;
- push sources are independently reconciled against provider state;
- partial quarantines degrade health and remain visible;
- reconciliation gaps and useful-evidence ratios are observable;
- every installed pull adapter passes the common end-to-end and failure contract;
- every enabled push transport passes authentication, replay, idempotency and recovery tests;
- provider fixtures cover parser drift and known false-positive patterns;
- operators can independently disable, subscribe/unsubscribe, reconcile, backfill and smoke-test a configured source;
- Century Games remains disabled unless permission is actually recorded;
- documentation, capability catalogue, operations guidance and delivery ledger match the implemented behavior;
- repository CI is green.