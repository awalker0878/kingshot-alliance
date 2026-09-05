# Gift Code source acquisition operations

Status: Current pre-deployment runbook

This runbook covers activation, scheduled acquisition, provider throttling, checkpoints and incident handling for registered Gift Code sources. It supplements the general Gift Code operations guide and the authoritative source-acquisition contract.

## Activation

Registering a source does not activate it. `ingestion_enabled=true` is accepted only when `EvaluateGiftCodeSourceActivationReadiness` reports every required check ready.

Before activation, confirm:

1. the installed adapter is the intended documented transport;
2. canonical domain and provider identity are exact;
3. required server-side credentials are configured;
4. required provider/platform permission is confirmed;
5. the machine-readable provider contract is documented where applicable;
6. source policy is complete;
7. automatic verification, if requested, is allowed by the source/trust boundary.

Generic JSON/RSS feeds require `provider_contract_confirmed=true`. Structured HTML requires `structured_contract_confirmed=true` and is limited to explicit machine-readable `data-gift-code*` markup. These flags do not authorize prose scraping.

## Runtime controls

The scheduled command remains:

```text
gift-codes:ingest-approved-sources --limit=25 --cycle
```

Use `--source=<source-key>` for a targeted operator run. The runtime applies both observation and provider-page bounds.

Relevant configuration:

- `GIFT_CODES_INGESTION_BATCH_SIZE`
- `GIFT_CODES_INGESTION_MAX_PAGES_PER_SOURCE`
- `GIFT_CODES_INGESTION_TIMEOUT_SECONDS`
- `GIFT_CODES_INGESTION_RETRY_BASE_SECONDS`
- `GIFT_CODES_INGESTION_RETRY_MAX_SECONDS`

A source whose `next_eligible_ingestion_at` is in the future is skipped rather than repeatedly polling a provider during a backoff window.

## Checkpoints and cursors

Source acquisition state is durable and provider-specific:

- X uses post-id `since_id` high water;
- Discord uses message-snowflake `after` high water;
- YouTube, Reddit, Facebook and Instagram retain documented opaque paging tokens/checkpoints;
- complete-document JSON/RSS/structured feeds remain cursorless and rely on retrieval/content fingerprints plus provenance idempotency.

Never hand-edit a checkpoint to manufacture evidence. Checkpoints are acquisition state only and do not grant trust.

## Health states

Activation and health are intentionally separate. Enabled sources may report:

- `pending`
- `healthy`
- `idle`
- `rate_limited`
- `authentication_failed`
- `permission_revoked`
- `contract_changed`
- `parser_failed`
- `degraded`

`idle` means a successful provider check returned no new observations. It must not be treated as a provider failure.

The source-management surface exposes readiness, current health, retry eligibility, request/observation/duplicate/rate-limit counters, recent ingestion runs and stable failure codes.

## Rate limiting and retry

When a provider supplies `Retry-After`, the runtime honours it. Otherwise bounded exponential backoff is calculated from the configured retry base/max values. A 429 increments rate-limit diagnostics and sets a future eligibility time.

Do not bypass provider backoff with repeated manual runs unless the provider restriction has been cleared and the source policy/credentials have been corrected.

## Failure response

For `authentication_failed`:

1. rotate or restore the provider credential;
2. verify the credential remains scoped to the configured identity;
3. rerun activation/readiness checks;
4. run one targeted source acquisition.

For `permission_revoked`:

1. restore provider/platform permission;
2. reconfirm the registered identity/scope;
3. run one targeted acquisition.

For `contract_changed` or `parser_failed`:

1. keep ingestion disabled or allow the retry window to hold the source;
2. compare the current provider response with the documented machine contract;
3. update policy/parser code only when the provider contract is legitimate and documented;
4. never fall back to generic prose scraping or an undocumented endpoint.

For a compromised or no-longer-approved source, revoke the source and run bounded source-policy reconciliation. Historical provenance remains append-only.

## Provider boundaries

Supported automation is restricted to the documented/authorized transports already represented by installed adapters. Do not introduce:

- undocumented/private provider endpoints;
- browser/session replay;
- Discord user-token/self-bot access;
- arbitrary Wiki/editorial/social page scraping;
- Gift Code Center reverse engineering or automated redemption.

Sources without a legitimate automated transport use registered-source manual evidence under their own source identity.

## Deployment verification

Before enabling external sources in a deployment:

1. run a fresh database installation;
2. configure only the provider credentials actually approved for that environment;
3. register sources with ingestion disabled;
4. verify activation-readiness diagnostics;
5. enable one source at a time;
6. run targeted acquisition and inspect recent runs/checkpoints;
7. confirm expected provenance/trust behavior;
8. then allow scheduled ingestion.

The repository is currently pre-deployment with an empty database, so source-acquisition state is defined directly in the base Gift Code schema without compatibility migrations or upgrade shims.
