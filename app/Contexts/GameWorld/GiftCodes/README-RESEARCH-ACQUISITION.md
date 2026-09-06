# Research-backed acquisition implementation status

Status: Current complete repository capability for a fresh pre-deployment schema.

The Gift Code acquisition program is implemented without compatibility shims, legacy cursor migration paths or dual-write transitions.

Delivered runtime capabilities include:

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
- non-ingesting provider smoke checks required before first automated activation;
- explicit, independent acquisition and authority-promotion controls;
- platform-admin operational alerts and controls for acquisition modes, push subscriptions, reconciliation and backfill;
- one canonical observation/provenance/trust/fact-reconciliation path for push, pull and registered evidence;
- shared pull-adapter conformance coverage across the complete installed adapter registry;
- shared push-transport conformance for authenticated reservation, replay idempotency, completion, revocation and security counters;
- provider failure normalization, parser-drift/malformed-document fixtures, identity-mismatch coverage and structured false-positive resistance;
- rebuildable observation correlation and source-performance projections that remain advisory to trust;
- global and per-source Time-to-Code/effectiveness metrics where real publication timestamps exist;
- hourly bounded acquisition-intelligence rebuilding;
- an operator acquisition surface for smoke, head, reconciliation, backfill, activation/authority controls, health and effectiveness;
- a fast registered-source manual evidence path for availability, invalidity, expiry, reward and applicability facts without generic scraping;
- evidence-qualified expiry precision, reward and applicability handling through the existing canonical fact pipeline.

The CLI registrations live in `routes/console.php`, matching the application command-registration pattern. Discord Gateway is a long-running operator/process-supervisor command and is intentionally not scheduled as a periodic short-lived task; head acquisition and reconciliation run on their bounded schedules, historical backfill and acquisition-intelligence rebuilding run independently.

The dedicated `Gift Code Verification` workflow makes fresh schema, Pint, Larastan, all GiftCodes V3 backend suites, pull/push conformance, provider failure/parser-drift coverage, frontend lint/format/type checks and production frontend build explicit release gates in addition to the repository-wide CI/security/architecture gates.

## Production activation boundary

Repository implementation completion is deliberately separate from activation of real third-party accounts. A deployment may ship with researched sources disabled and still have a complete application capability.

An external source becomes active only after an operator has the actual provider identity, required permission/contract, environment credentials or entitlements, a recent passing non-ingesting smoke check and an explicit acquisition enablement decision. Authority promotion is enabled separately.

Century Games remains fail-closed until express provider permission or a cooperative contract is actually recorded. The existence of a public RSS endpoint is not treated as authorization. X, Discord, YouTube and Meta activation similarly depends on real provider access and cannot be fabricated by repository code.

See `docs/product/gift-code-research-backed-acquisition-plan.md`, `docs/reference/gift-code-source-acquisition.md` and `docs/operations/gift-code-source-acquisition.md` for the closed product, reference and operator contracts.
