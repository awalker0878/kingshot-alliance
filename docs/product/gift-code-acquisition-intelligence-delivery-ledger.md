# Gift Code Acquisition, Intelligence & Operations — Delivery Ledger

Status: Closed repository implementation program for the fresh pre-deployment schema.

Canonical product contract: [Gift Code research-backed source acquisition plan](gift-code-research-backed-acquisition-plan.md).
Canonical source reference: [Gift Code source acquisition reference](../reference/gift-code-source-acquisition.md).
Operator runbook: [Gift Code source acquisition operations](../operations/gift-code-source-acquisition.md).

A row is `Complete` only when its implementation, authorization/trust boundary, boundedness/idempotency, operational visibility and acceptance coverage are present. Real third-party source activation is intentionally an environment/operator approval step and is not fabricated as repository state.

| ID | Status | Delivered outcome | Acceptance evidence |
| --- | --- | --- | --- |
| GCA-01 | Complete | Independent `head`, `reconciliation` and `backfill` durable synchronization state | `GiftCodeSourceSyncState`, sync repository, acquisition/reconciliation/backfill V3 tests |
| GCA-02 | Complete | High-water safety: provider/parser failure cannot advance committed source state | shared pull conformance and source-acquisition hardening tests |
| GCA-03 | Complete | Pull adapter registry and common acquisition contract across all ten installed adapters | `GiftCodePullAdapterConformanceV3Test` plus provider-specific suites |
| GCA-04 | Complete | Durable push reservation, authentication/signature boundaries, replay idempotency, explicit completion and revocation | shared push transport conformance plus YouTube/Facebook/X/Discord transport suites |
| GCA-05 | Complete | Independent reconciliation for push-enabled sources and observable reconciliation gaps | reconciliation actions, gap counters and transport tests |
| GCA-06 | Complete | Stable provider failure normalization, backoff/rate-limit diagnostics and identity failures | `GiftCodeProviderFailureMatrixV3Test` and source health tests |
| GCA-07 | Complete | Parser-drift/malformed-document failure, structured false-positive resistance and bounded quarantine | provider failure matrix, source-adapter and acquisition-hardening tests |
| GCA-08 | Complete | Non-ingesting live-read smoke check with persisted bounded diagnostics | `RunGiftCodeSourceSmokeCheck`, activation control tests and operations UI |
| GCA-09 | Complete | Acquisition enablement is independent from `authority_promotion_enabled` | source management actions, activation readiness and HTTP tests |
| GCA-10 | Complete | Rebuildable observation clusters distinguish observations, distinct publishers, independent sources and official sources | cluster rebuild action/models and acquisition-intelligence tests |
| GCA-11 | Complete | Time-to-Code effectiveness with real publication-time evidence only | acquisition statistics/effectiveness query and projection tests |
| GCA-12 | Complete | Advisory source-performance projection for usefulness, first discovery, latency, quarantine/duplicate/conflict and productivity | source performance rebuild action/model/query coverage |
| GCA-13 | Complete | Hourly bounded acquisition-intelligence maintenance | `GiftCodesServiceProvider` scheduler integration and command/action tests |
| GCA-14 | Complete | Platform operations dashboard for smoke/head/reconcile/backfill, activation/authority, health and effectiveness | `SourceOperations.vue`, controller/routes, localization and frontend verification |
| GCA-15 | Complete | Registered-source manual evidence workflow for availability, invalidity, expiry, reward and applicability | `SourceEvidence.vue`, `RecordRegisteredGiftCodeEvidence`, HTTP/behavior coverage |
| GCA-16 | Complete | Reward/applicability and expiry precision remain evidence-qualified facts, not curator shortcuts | canonical fact reconciler/presenter and GiftCode behavior/workspace suites |
| GCA-17 | Complete | Century Games and provider/platform sources fail closed without real permission/identity/credential gates | researched-source/adapters tests and activation readiness checks |
| GCA-18 | Complete | No generic scraping, undocumented redemption API, self-bot/user-token automation or trust shortcut introduced | source-policy contracts, adapter grammar tests, owner/reference documentation |
| GCA-19 | Complete | Product/reference/operations/implementation documentation reconciled to current architecture | acquisition plan, source reference, runbook and implementation-status README |
| GCA-20 | Complete | Dedicated release gate covers fresh schema, Pint, Larastan, GiftCodes V3 tests, frontend lint/format/type and production build | `.github/workflows/gift-code-verification.yml`; repository-wide CI/security/architecture gates remain required before merge |

## Closure boundary

The repository program is closed when GCA-01 through GCA-20 are complete and the containing pull request passes the dedicated Gift Code Verification plus all applicable repository-wide gates before merge.

Production activation does not reopen the repository program merely because a provider account remains disabled. Activation requires the actual provider identity, current permission/contract, environment credentials/entitlements, a recent passing non-ingesting smoke check and explicit operator enablement. Authority promotion remains a separate explicit decision.

Additional source adapters are future product work only when measured acquisition effectiveness demonstrates a coverage gap. They are not a hidden closeout requirement.
