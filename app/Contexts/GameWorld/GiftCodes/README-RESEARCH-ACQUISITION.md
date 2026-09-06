# Research-backed acquisition implementation status

This branch implements the research-backed Gift Code acquisition architecture for a fresh deployment. Compatibility shims, legacy cursor migration paths and dual-write transitions are intentionally excluded.

Implemented runtime capabilities now include:

- independent head, reconciliation and historical-backfill synchronization state;
- truthful degraded health for partial quarantine plus accepted/quarantined/duplicate usefulness metrics;
- atomic X incremental pagination/high-water handling;
- permission-gated Century Games RSS extraction with explicit code/expiry evidence and conditional HTTP retrieval;
- YouTube WebSub discovery with canonical Data API fetch and REST reconciliation/backfill;
- Facebook Page signed webhook discovery with canonical Graph fetch and pull reconciliation;
- Discord Gateway discovery with approved guild/channel/author boundaries and REST canonical-message recovery;
- entitlement-gated X Filtered Stream webhook delivery with CRC/signature verification and canonical Post retrieval;
- durable push-delivery replay/idempotency records, subscription state and reconciliation-gap detection;
- bounded historical backfill independent from freshness polling;
- platform-admin operational alerts and source controls for acquisition modes, push subscriptions, reconciliation and backfill;
- one canonical observation/provenance/trust/fact-reconciliation path for push, pull and registered evidence.

The CLI registrations live in `routes/console.php`, matching the application command-registration pattern. Discord Gateway is a long-running operator/process-supervisor command and is intentionally not scheduled as a periodic short-lived task; reconciliation and backfill are scheduled separately.

Remaining closeout work is tracked in `docs/product/gift-code-research-backed-acquisition-plan.md`: common pull/push conformance coverage, broader provider failure/parser-drift fixtures, non-ingesting live smoke checks, documentation/delivery-ledger reconciliation and full green CI.

Century Games remains fail-closed until express provider permission or a cooperative contract is actually recorded. The existence of the public RSS endpoint is not treated as authorization.
