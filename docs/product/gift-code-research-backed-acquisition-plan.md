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

## Delivery order

1. Replace the single ingestion cursor with explicit per-source synchronization state for head, incremental, reconciliation and backfill modes.
2. Correct partial-quarantine health so completed-with-quarantine sources surface as degraded and retain evidence-quality diagnostics.
3. Productionize the permissioned Century Games source as a richer first-party adapter that can extract explicit availability and expiry evidence from real Kingshot publication content.
4. Introduce a shared conservative Gift Code evidence extractor for explicit code labels, expiry, applicability and reward facts.
5. Build a real-publication fixture corpus and false-positive corpus.
6. Introduce first-class push delivery/subscription infrastructure that feeds the existing canonical observation ingestion path.
7. Add YouTube WebSub push-first discovery with Data API reconciliation/backfill.
8. Correct X incremental pagination/high-water atomicity, then add entitlement-gated real-time X transport.
9. Add Discord Gateway push-first discovery with REST reconciliation.
10. Add Facebook Page webhook discovery where the configured Meta application has the required Page subscription access; retain Graph polling for reconciliation/backfill.
11. Keep Instagram poll-first unless Meta exposes a documented own-media publication event suitable for this source; separate head and backfill state.
12. Keep Reddit independent/discovery-only and separate head/backfill state.
13. Add conditional HTTP retrieval to document/feed adapters.
14. Require every pull adapter to pass a shared end-to-end conformance contract.
15. Require every push transport to pass authentication, replay, idempotency, crash recovery and reconciliation-gap tests.
16. Complete the provider failure matrix and parser-drift fixture tests.
17. Extend health with useful-evidence, subscription and reconciliation-gap metrics.
18. Add operator alerting, source controls and live non-ingesting smoke checks.
19. Roll out push transports in shadow/canary mode while retaining reconciliation.
20. Reconcile product/reference/operations documentation and close the delivery ledger.

## Authority boundaries

Research catalogue membership never grants source authority. Official-source automatic verification requires configured source identity, current activation readiness, legitimate provider access, successful transport authentication where applicable, canonical source validation and an explicit evidence grammar match. Independent sources remain corroboration/discovery unless an explicit source policy grants otherwise.

## Century Games

Century Games permission is confirmed for this implementation. The provider permission gate remains as an explicit operational contract, but it is no longer a delivery blocker. The adapter should prefer a stable permissioned structured contract when available and otherwise consume the approved Century Games RSS publishing path with conditional retrieval and canonical source validation.

## Definition of complete

The source acquisition subsystem is complete when freshness state is independent of historical paging, no high-water mark can move past unprocessed provider content, push sources are reconciled against provider state, official Century Games content yields explicit code/expiry evidence, all adapters share evidence semantics, every adapter and push transport passes end-to-end conformance, partial quarantines degrade health, reconciliation gaps are observable, and operators can independently disable, reconcile, backfill and smoke-test each configured source.