# Gift Code source acquisition operations

Status: Current pre-deployment runbook

This runbook covers source registration, non-ingesting verification, activation, scheduled acquisition, push/reconciliation completeness, provider throttling, derived acquisition intelligence and incident handling. It supplements the general Gift Code operations guide and the authoritative source-acquisition contract.

## Activation sequence

Registering a source does not activate acquisition or authority. Acquisition and authority promotion are separate controls.

Before first acquisition activation:

1. register the source with acquisition disabled;
2. confirm the installed adapter is the intended documented transport;
3. confirm canonical domain and provider identity are exact;
4. configure only provider credentials approved for that environment;
5. record required provider/platform permission and machine-contract gates;
6. run a non-ingesting smoke check from `/platform/gift-codes/sources/operations`;
7. inspect the persisted smoke result and provider identity diagnostics;
8. enable acquisition only after a recent passing smoke check;
9. enable authority promotion separately only when the registered source policy permits it;
10. retain reconciliation for push-enabled sources.

`RunGiftCodeSourceSmokeCheck` executes the installed adapter with a one-observation bound and deliberately discards observations. It does not write Gift Code provenance, trust, catalogue state or synchronization cursors. `SetGiftCodeSourceAcquisitionControls` requires the most recent passing smoke check to be within `GIFT_CODES_SOURCE_SMOKE_CHECK_MAX_AGE_HOURS` before first activation.

Generic JSON/RSS feeds require `provider_contract_confirmed=true`. Structured HTML requires `structured_contract_confirmed=true` and is limited to explicit machine-readable `data-gift-code*` markup. These flags do not authorize prose scraping. Century Games additionally remains fail-closed without express provider permission or a cooperative contract.

## Operator surfaces

- `/platform/gift-codes/sources` manages registered source identity, provider-policy gates and push subscription configuration.
- `/platform/gift-codes/sources/operations` shows activation, health, smoke diagnostics, transport failures, source performance and global acquisition effectiveness; Platform Administrators can smoke-test, head-fetch, reconcile, backfill, enable/disable acquisition and independently toggle authority promotion where allowed.
- `/platform/gift-codes/sources/evidence-entry` records curator-confirmed registered-source availability, invalidity, expiry, reward and applicability evidence. Reward/applicability assertions still pass through normal evidence qualification and conflict handling.

The operations surface never turns acquisition metrics into trust. Source-performance and correlation tables are rebuildable projections over append-only evidence.

## Scheduled runtime

The existing acquisition commands remain:

```text
gift-codes:ingest-approved-sources --limit=25 --cycle
gift-codes:reconcile-sources --limit=25
gift-codes:backfill-sources --limit=5
```

Head ingestion and reconciliation run every 15 minutes; historical backfill runs hourly. `GiftCodesServiceProvider` also schedules `gift-codes:rebuild-acquisition-intelligence` hourly as a bounded projection rebuild. Discord Gateway remains a long-running supervised process rather than a short periodic schedule.

Use `--source=<source-key>` on the acquisition/reconciliation/backfill commands where supported for a targeted operator run. The runtime applies both observation and provider-page bounds.

Relevant configuration:

- `GIFT_CODES_INGESTION_BATCH_SIZE`
- `GIFT_CODES_INGESTION_MAX_PAGES_PER_SOURCE`
- `GIFT_CODES_INGESTION_TIMEOUT_SECONDS`
- `GIFT_CODES_INGESTION_RETRY_BASE_SECONDS`
- `GIFT_CODES_INGESTION_RETRY_MAX_SECONDS`
- `GIFT_CODES_SOURCE_SMOKE_CHECK_MAX_AGE_HOURS`

A source whose `next_eligible_ingestion_at` is in the future is skipped rather than repeatedly polling a provider during a backoff window.

## Synchronization and completeness

Acquisition state is durable and mode-specific:

- head state owns freshness polling;
- reconciliation state is independent from head discovery and proves completeness for push sources;
- backfill state is independent from freshness and can be restarted without rewinding live head state;
- X uses post-id high water and drains bounded incremental pages before committing the high-water mark;
- Discord uses message-snowflake high water;
- YouTube, Reddit, Facebook and Instagram retain documented opaque provider paging/checkpoint state;
- complete-document JSON/RSS/structured feeds use retrieval/content fingerprints and provenance idempotency rather than manufacturing cursors.

No high-water mark is advanced when provider-page acquisition fails. Repeated provider content is idempotent at the canonical provenance boundary. Revoked sources are excluded from both pull acquisition and push-delivery reservation.

Never hand-edit a checkpoint to manufacture evidence. Checkpoints are acquisition state only and do not grant trust.

## Push delivery contract

Push is a latency optimization and never a trust grant. Enabled push paths persist an authenticated delivery reservation before canonical processing. Replay keys are source-scoped and idempotent; duplicate reservation cannot reset a previous terminal processing result. Signature failures, replay rejections and received-event timestamps are projected independently into source health.

Provider-specific push boundaries remain:

- YouTube WebSub signal -> canonical Data API fetch;
- Facebook signed Page webhook -> canonical Graph fetch;
- X entitlement-gated Filtered Stream webhook -> canonical Post fetch;
- Discord Gateway bot event -> approved REST message recovery/reconciliation.

Reconciliation remains enabled when push is enabled so missed events are observable as reconciliation gaps.

## Health and acquisition intelligence

Activation and health are intentionally separate. Enabled sources may report `pending`, `healthy`, `idle`, `rate_limited`, `authentication_failed`, `permission_revoked`, `contract_changed`, `parser_failed` or `degraded`.

`idle` means a successful provider check returned no new observations. It is not a provider failure.

Operational projections include:

- accepted, duplicate and quarantined observations;
- useful-observation and quarantine ratios;
- unique Gift Codes and first discoveries per source;
- confirmed-correct, confirmed-incorrect and conflicting observations;
- median discovery/confirmation latency where qualified;
- median and P95 Time-to-Code where both publication time and first qualifying observation are known;
- source mix across official and independent observations;
- signature failures, replay rejections and reconciliation gaps.

Unknown timing remains unknown; missing publication timestamps are not replaced with ingestion time. These projections can be rebuilt without changing trust, provenance or Gift Code status.

## Rate limiting and retry

When a provider supplies `Retry-After`, the runtime honours it. Otherwise bounded exponential backoff is calculated from the configured retry base/max values. A 429 normalizes to `rate_limited`, increments rate-limit diagnostics and sets a future eligibility time.

Provider response normalization used by the acquisition contract is stable: 401 `authentication_failed`, 403 `permission_revoked`, 404 `source_identity_unavailable`, 408 `provider_timeout`, 409 `provider_conflict`, 429 `rate_limited`, 5xx `provider_unavailable`, and other non-success responses `source_retrieval_failed`.

Do not bypass provider backoff with repeated manual runs unless the provider restriction has been cleared and source policy/credentials have been corrected.

## Failure response

For `authentication_failed`, restore/rotate the credential, verify its identity scope, run a smoke check, then perform one targeted acquisition.

For `permission_revoked`, restore the legitimate provider/platform permission, reconfirm registered identity/scope, run a smoke check, then reactivate if appropriate.

For `contract_changed` or `parser_failed`, keep acquisition disabled or let the retry window hold the source, compare the provider response with the documented contract, and update parser/policy only when the machine contract remains legitimate. Never fall back to generic prose scraping or an undocumented endpoint.

For a compromised or no-longer-approved source, revoke it and run bounded source-policy reconciliation. Historical provenance remains append-only.

## Verification gates

The repository has a dedicated `Gift Code Verification` workflow. It validates a fresh schema, Pint, Larastan, all GiftCodes V3 backend suites, the shared pull-adapter conformance suite, shared push-transport conformance suite, provider failure/parser-drift fixtures, frontend lint/format/type contracts and a production frontend build.

The shared contracts explicitly verify cursor safety, pull idempotency, revocation, durable push reservation, replay idempotency, explicit completion, security counters, normalized provider failures, malformed-contract failure and false-positive resistance.

## Provider boundaries

Supported automation is restricted to documented/authorized transports represented by installed adapters. Do not introduce undocumented/private provider endpoints, browser/session replay, Discord user-token/self-bot access, arbitrary Wiki/editorial/social prose scraping, Gift Code Center reverse engineering or automated redemption.

Sources without a legitimate automated transport use registered-source manual evidence under their own source identity.

## Deployment verification

Before enabling external sources in a deployment:

1. install the fresh database schema;
2. configure only provider credentials actually approved for the environment;
3. register sources with acquisition disabled;
4. verify activation-readiness diagnostics;
5. run non-ingesting smoke checks;
6. enable one source at a time while retaining reconciliation;
7. inspect head/reconciliation/backfill state and recent health;
8. confirm expected provenance/trust/fact behavior;
9. enable push only where provider entitlement/permission is confirmed;
10. keep Century Games disabled unless express permission/cooperation is recorded.

The repository is pre-deployment with an empty database, so source-acquisition state is defined directly for the fresh deployment without compatibility shims or upgrade paths.
