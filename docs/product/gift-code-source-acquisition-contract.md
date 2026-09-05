# Gift Code source acquisition contract

Status: **pre-deployment authoritative contract**

The Gift Code source system separates research, source approval, provider readiness, acquisition health and trust. No installed adapter or research-catalogue row grants source authority.

## Lifecycle

```text
research catalogue
    -> source registered
    -> source configured
    -> activation readiness evaluated
    -> ingestion enabled
    -> provider observations acquired
    -> append-only provenance recorded
    -> trust and fact projections reconciled
```

A source can be registered and configured while ingestion remains disabled. Enabling ingestion is a fail-closed operation.

## Activation states

`gift_code_sources.activation_status` uses the following operational states:

- `registered` — source identity exists but no acquisition adapter is selected;
- `configured` — adapter/policy are present but scheduled ingestion is disabled;
- `enabled` — activation readiness passed and scheduled ingestion is enabled;
- `revoked` — source was explicitly revoked and cannot ingest.

An enabled source does not imply healthy acquisition and does not imply automatic verification.

## Health states

`gift_code_sources.health_status` is independent of activation state:

- `disabled` — ingestion is not active;
- `pending` — enabled but not successfully checked yet;
- `healthy` — the latest successful acquisition produced observations;
- `idle` — the latest successful acquisition produced no observations;
- `rate_limited` — provider returned a throttling response;
- `authentication_failed` — configured credentials are no longer accepted;
- `permission_revoked` — the provider denied the required permission;
- `contract_changed` — configured identity/policy no longer matches the provider response;
- `parser_failed` — retrieved content violates the supported machine-readable contract;
- `degraded` — another provider/retrieval failure occurred.

"No new Gift Codes" is therefore distinguishable from "the integration is broken."

## Activation readiness

`EvaluateGiftCodeSourceActivationReadiness` evaluates explicit checks:

- `source_active`;
- `adapter_installed`;
- `canonical_domain_valid`;
- `identity_valid`;
- `credentials_available`;
- `permissions_valid`;
- `provider_contract_valid`;
- `policy_complete`;
- `verification_boundary_valid`.

`ManageGiftCodeSourceRegistry` refuses `ingestion_enabled=true` unless every required check passes.

Provider-specific requirements include:

- X: account identity, configured bearer token and confirmed API access;
- Discord: guild/channel/author identity, installed bot token, channel permission and message-content access;
- YouTube: channel identity, API key and confirmed Data API access;
- Reddit: independent classification, OAuth credentials, confirmed Data API access, and automatic verification disabled;
- Facebook: Page identity, access token and confirmed Page/platform permission;
- Instagram: professional-account identity, access token and confirmed API permission;
- Century Games RSS: approved feed path/category and provider permission;
- generic JSON/RSS: documented provider machine-readable contract;
- structured HTML: explicitly documented structured-markup contract. Generic prose scraping is not a supported contract.

## Acquisition result contract

Every pull adapter returns `GiftCodeIngestionPage` with:

- bounded observations;
- continuation/high-water cursor where applicable;
- retrieval version;
- provider request id where exposed;
- rate-limit/quota metadata where exposed;
- durable source checkpoint;
- provider request count.

A checkpoint can preserve provider-specific diagnostic state such as channel id, feed version, latest observed media/post id or current provider paging cursor. It is diagnostic/acquisition state only and does not become evidence authority.

## Bounded execution

`RunApprovedGiftCodeSourceIngestion` applies both an observation bound and a page bound. The runtime rejects adapters that exceed the requested observation limit or repeat a continuation cursor indefinitely.

Configuration:

- `GIFT_CODES_INGESTION_BATCH_SIZE` — maximum observations examined for one source sweep;
- `GIFT_CODES_INGESTION_MAX_PAGES_PER_SOURCE` — maximum provider pages followed in one source sweep;
- `GIFT_CODES_INGESTION_TIMEOUT_SECONDS` — provider request timeout.

## Failure and retry semantics

Provider HTTP failures are classified before they reach the scheduler. The runtime honours provider `Retry-After` where available and otherwise applies bounded exponential backoff.

Configuration:

- `GIFT_CODES_INGESTION_RETRY_BASE_SECONDS`;
- `GIFT_CODES_INGESTION_RETRY_MAX_SECONDS`.

A source with `next_eligible_ingestion_at` in the future is skipped by scheduled ingestion. A 429 increments the source rate-limit counter and does not cause immediate repeated polling.

Authentication, permission and contract failures remain visible as health states; they are not silently converted into empty successful pages.

## Provider identity and evidence

Acquisition must establish the configured provider identity before accepting observations when the provider supports an identity lookup. Evidence retains source URLs, source/retrieval/parser versions and content fingerprints.

Provider transport success does not automatically make evidence authoritative. `auto_verify` remains source policy and is independently constrained by the trust model.

## Incremental acquisition

Where the documented provider API supports a stable high-water identifier, use it:

- X uses post id `since_id`;
- Discord uses message snowflake `after`.

Where the documented provider API exposes opaque paging tokens, preserve those tokens and provider checkpoints rather than inventing undocumented high-water behavior:

- YouTube uploads playlist;
- Reddit listings;
- Facebook Page posts;
- Instagram media.

Generic complete-document feeds remain cursorless and rely on retrieval/content fingerprints plus provenance deduplication.

## Explicit exclusions

This contract never authorizes:

- generic prose scraping;
- undocumented/private APIs;
- login/session replay;
- Discord self-bots or user tokens;
- Gift Code Center endpoint reverse engineering;
- automated redemption;
- research catalogue entries automatically creating, classifying or enabling sources;
- automatic verification merely because an adapter parsed an observation.

## Pre-deployment schema policy

The database is not deployed and contains no legacy data. Acquisition state is therefore defined directly in the base Gift Code schema rather than through compatibility migrations, transitional columns or upgrade shims.
