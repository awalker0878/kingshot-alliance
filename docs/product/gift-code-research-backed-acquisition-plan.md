# Gift Code research-backed source acquisition plan

Status: Current complete repository program for a fresh pre-deployment schema.

Baseline: `main` at `b7dd632f51d9e3fe63bf9bbbae25c7a71e1f44c5`.

This implementation treats the deployment as fresh: schema changes are made directly, with no migration compatibility shims or dual-write transition paths.

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

## Delivery order and closeout status

1. **Complete:** explicit per-source synchronization state for independent head, reconciliation and backfill modes replaces the retired single-cursor design.
2. **Complete:** partial-quarantine health surfaces degradation and retains evidence-quality diagnostics.
3. **Complete behind permission gate:** Century Games has a richer first-party adapter capable of explicit availability/expiry extraction only when legitimate provider permission/cooperation is recorded.
4. **Complete:** shared conservative evidence extraction covers explicit code labels, expiry, applicability and reward facts.
5. **Complete:** real-shape, false-positive, malformed-document and parser-drift fixture coverage is part of the provider matrix.
6. **Complete:** durable push delivery/subscription infrastructure feeds the canonical observation ingestion path.
7. **Complete:** YouTube WebSub push-first discovery performs canonical Data API retrieval, reconciliation and backfill.
8. **Complete:** X has atomic incremental pagination/high-water handling and entitlement-gated Filtered Stream transport; timeline polling remains reconciliation/fallback.
9. **Complete:** Discord Gateway provides push-first discovery with REST canonical-message retrieval and reconciliation.
10. **Complete where Meta permissions allow:** Facebook Page webhook discovery validates signed delivery and retrieves canonical Graph content; Graph polling remains reconciliation/backfill.
11. **Intentionally poll-first:** Instagram remains poll-first until a suitable documented own-media publication event is verified and approved for the configured account.
12. **Intentionally independent/discovery-only:** Reddit has separate freshness/backfill state and can never auto-verify Gift Codes.
13. **Complete:** structured feeds support conditional HTTP using ETag/Last-Modified with explicit 304 idle semantics.
14. **Complete:** every installed pull adapter is covered by the common registration/resolution contract, and runner conformance verifies cursor safety, idempotency and revocation.
15. **Complete:** push transport conformance verifies authentication/signature boundaries, durable reservation, replay idempotency, explicit completion, revocation, security counters and reconciliation-gap behavior.
16. **Complete:** common provider failure normalization, malformed/parser-drift and identity-mismatch fixtures fail closed.
17. **Complete:** useful-evidence, duplicate, quarantine, subscription, signature/replay and reconciliation-gap metrics are visible operationally.
18. **Complete:** operator alerting/source controls and non-ingesting provider smoke checks are implemented; first automated activation requires a recent passing smoke check.
19. **Complete rollout contract:** push transports are independently enableable and retain reconciliation; real provider activation remains an environment/operator decision rather than repository implementation.
20. **Complete:** product, reference, operations and implementation-status documentation are reconciled to the mode-specific synchronization and acquisition-intelligence architecture, with release closure enforced by the dedicated Gift Code Verification and repository-wide gates.

## Acquisition intelligence and effectiveness

The program extends acquisition beyond transport plumbing so operators can measure whether the system discovers useful Gift Codes promptly and reliably.

### Time-to-Code

The primary acquisition KPI is Time-to-Code: the elapsed time between a source publication timestamp actually supported by evidence and the application's earliest qualifying observation of the code.

The projections expose sample counts plus median and P95 values when enough real timestamp evidence exists. Missing source publication time remains unknown; ingestion time is never substituted merely to manufacture a latency metric.

### Correlation and source independence

`GiftCodeObservationCluster` is a rebuildable observational projection keyed by normalized Gift Code. It separates observation count, distinct source count, independent-source count and official-source count, records the earliest observed source/timestamp and derives Time-to-Code where supported.

Correlation is intentionally not causal provenance. Multiple observations from one publisher do not become multiple independent corroborators, and the projection does not assert that one publisher copied another without evidence.

### Source performance

`GiftCodeSourcePerformanceProjection` is an advisory, rebuildable projection containing useful operational measures such as observations, unique codes, first discoveries, qualified/correct/incorrect/conflicting observations, discovery and confirmation latency, Time-to-Code, useful/quarantine/duplicate ratios and last productive observation.

Source performance never replaces `GiftCodeTrustResolver`, never auto-approves a source and never changes source authority by itself.

## Operator experience

The closed program exposes three complementary platform surfaces:

- `/platform/gift-codes/sources` for registered source identity, policy and push configuration;
- `/platform/gift-codes/sources/operations` for smoke diagnostics, activation/health, head/reconciliation/backfill actions, source performance and global effectiveness;
- `/platform/gift-codes/sources/evidence-entry` for intentional registered-source manual evidence when a legitimate automated contract does not exist.

Acquisition and `authority_promotion_enabled` are separate controls. Enabling polling, push, reconciliation or backfill can never implicitly increase a source's trust authority.

The manual-evidence workflow accepts exact registered-domain publication evidence for availability, invalidity, expiry, rewards and applicability. Reward/applicability claims continue through the normal qualification/conflict pipeline; the curator form is not a shortcut around canonical facts.

## Authority boundaries

Research catalogue membership never grants source authority. Official-source automatic verification requires configured source identity, current activation readiness, legitimate provider access, successful transport authentication where applicable, canonical source validation and an explicit evidence grammar match. Independent sources remain corroboration/discovery unless an explicit source policy grants otherwise.

A passing smoke check demonstrates bounded provider-read readiness only. It does not grant source authority, qualify evidence or change Gift Code trust.

## Provider-specific boundaries

### Century Games

The public Century Games RSS endpoint demonstrates a machine-readable publishing surface, but its existence does **not** establish permission to ingest it. The existing `provider_permission_confirmed` activation gate remains authoritative.

Until express permission or a cooperative feed/webhook contract is actually confirmed, the Century Games adapter remains ingestion-disabled. If permission is granted, the approved feed path can emit explicit availability and expiry evidence with canonical publication links and conditional retrieval.

### X

The preferred real-time path is Filtered Stream delivery scoped to the confirmed official account. That path is enabled only when the configured X account has the required entitlement and secrets. The normal X user-post timeline adapter remains reconciliation/fallback, with high-water advancement committed only after bounded incremental provider content has been safely drained.

### YouTube

WebSub is the preferred discovery path. A WebSub event is only a discovery signal; the application retrieves canonical video metadata through the YouTube Data API before evidence extraction. REST polling remains reconciliation and historical backfill.

### Discord

A legitimately installed bot may use Gateway `MESSAGE_CREATE` events for low-latency discovery only within the approved guild/channel/author scope and with required message-content access. Canonical REST message retrieval and high-water reconciliation remain available for reconnect/completeness recovery. Self-bots and user-token automation are excluded.

### Meta

Facebook Page push is supported only for an application with the required Page access, webhook configuration and permission/App Review state. Signed webhook events identify candidate Page posts; canonical Graph content is retrieved before evidence extraction. Instagram remains polling-only until an equally appropriate documented publication event is verified and approved.

### Reddit

Reddit remains optional independent discovery. It never auto-verifies Gift Codes and a provider/API transition cannot degrade the core official-source capability.

## Definition of complete

The source-acquisition, intelligence and operations program is complete when all of the following are true:

- freshness state is independent from historical paging and reconciliation;
- no high-water mark can move past unprocessed provider content;
- push sources are independently reconciled against provider state;
- partial/parser quarantines remain visible and do not silently advance source state;
- reconciliation gaps and useful-evidence ratios are observable;
- every installed pull adapter is registered/resolvable and the common runner contract proves cursor safety, idempotency and revocation;
- every supported push transport passes authentication/replay/idempotency/recovery/revocation coverage;
- provider fixtures cover stable HTTP failure normalization, parser drift, malformed contracts, identity mismatch and known false-positive patterns;
- operators can independently disable, smoke-test, head-fetch, reconcile, backfill and control source authority;
- advisory correlation, Time-to-Code and source-performance projections are rebuildable and cannot influence canonical trust directly;
- registered-source manual evidence supports legitimate non-automated evidence without generic scraping;
- Century Games remains disabled unless permission is actually recorded;
- external-provider activation is explicitly treated as an environment/operator approval task rather than fabricated repository state;
- product/reference/operations documentation matches the implemented architecture;
- the dedicated Gift Code Verification workflow and all applicable repository release gates are green on the containing implementation candidate.

No additional generic scraper, trust status, alternate resolver, undocumented redemption API, CAPTCHA automation or source-count expansion is required for program closure. Additional sources are future product choices only when acquisition metrics demonstrate a real coverage gap.
