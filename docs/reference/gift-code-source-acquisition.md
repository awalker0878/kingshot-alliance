# Gift Code source acquisition reference

Status: Current pre-deployment reference

This reference describes the source-acquisition state that surrounds the canonical Gift Code provenance/trust pipeline. It does not define a second trust engine.

## Source lifecycle fields

`gift_code_sources` stores:

| Field | Meaning |
| --- | --- |
| `activation_status` | `registered`, `configured`, `enabled`, or `revoked` |
| `health_status` | Current provider/acquisition health independent of activation |
| `ingestion_cursor` | Durable continuation/high-water value used by the adapter |
| `ingestion_checkpoint` | Provider-specific diagnostic/acquisition state |
| `next_eligible_ingestion_at` | Earliest scheduled retry after provider/backoff constraints |
| `last_ingestion_attempt_at` | Most recent attempted acquisition |
| `last_ingestion_success_at` | Most recent successful provider acquisition |
| `last_ingestion_failure_at` | Most recent failed provider acquisition |
| `last_ingestion_failure_code` | Stable operational failure classification |
| `last_ingestion_error` | Bounded operator-facing failure detail |
| `last_provider_request_id` | Provider request/correlation identifier when exposed |
| `last_retrieval_version` | Provider/document retrieval version when exposed |
| `last_retry_after_seconds` | Last provider/runtime retry delay |
| `last_rate_limit_remaining` | Last observed provider rate-limit remainder |
| `last_quota_remaining` | Last observed provider quota remainder |
| `request_count` | Cumulative provider requests for this source |
| `observation_count` | Cumulative acquired observations |
| `duplicate_observation_count` | Cumulative provenance duplicates |
| `rate_limit_event_count` | Cumulative provider throttling events |
| `consecutive_failures` | Current consecutive source-level failure count |
| `last_observation_at` | Most recent acquisition that produced an observation |

These fields are operational state. They do not grant evidence authority.

## Ingestion-run fields

`gift_code_ingestion_runs` records one bounded source run with:

- source/result cursor;
- source/result checkpoint;
- page and request counts;
- examined/accepted/duplicate/quarantined counts;
- provider request id and retrieval version;
- retry-after/rate-limit/quota metadata;
- stable failure code/message;
- started/completed timestamps.

The source row contains the current operational projection; ingestion runs preserve bounded recent history.

## Activation readiness checks

`EvaluateGiftCodeSourceActivationReadiness` returns named checks:

- `source_active`
- `adapter_installed`
- `canonical_domain_valid`
- `identity_valid`
- `credentials_available`
- `permissions_valid`
- `provider_contract_valid`
- `policy_complete`
- `verification_boundary_valid`

`ManageGiftCodeSourceRegistry` rejects `ingestion_enabled=true` if any required check is not ready.

## Health values

Expected source health values are:

| Health | Meaning |
| --- | --- |
| `disabled` | Source is not ingesting |
| `pending` | Enabled but not successfully checked yet |
| `healthy` | Latest successful acquisition produced observations |
| `idle` | Latest successful acquisition produced no observations |
| `rate_limited` | Provider throttled acquisition |
| `authentication_failed` | Credential rejected/expired |
| `permission_revoked` | Required provider permission is unavailable |
| `contract_changed` | Provider identity/shape no longer matches approved contract |
| `parser_failed` | Response violates the supported machine-readable parser contract |
| `degraded` | Other retrieval/provider failure |

## Failure codes

The runner preserves typed acquisition failure codes where the adapter/provider can classify them. Generic runtime fallbacks remain stable and bounded, including adapter unavailable, unsupported source format, source policy rejection, source retrieval failure, unsupported observation format, observation policy rejection, and observation ingestion failure.

A failure code controls operations/diagnostics; canonical Gift Code status is still derived only from qualified provenance and moderation.

## Adapter cursor semantics

| Adapter family | Cursor/checkpoint behavior |
| --- | --- |
| X API | Post-id high water; request uses documented `since_id` |
| Discord | Message-snowflake high water; request uses documented `after` |
| YouTube | Provider paging token plus uploads/channel checkpoint |
| Reddit | Provider listing token/checkpoint |
| Facebook | Graph paging cursor/checkpoint |
| Instagram | Graph paging cursor/checkpoint |
| JSON feed | Provider cursor only when defined by the approved feed contract |
| RSS/Atom | Complete-document retrieval; no invented cursor |
| Structured HTML | Complete documented markup retrieval; no invented cursor |
| Century Games RSS | Complete approved feed retrieval; no invented cursor |

The runner rejects a repeated continuation cursor within one bounded sweep to prevent infinite provider paging loops.

## Provider contract gates

Generic automation is not equivalent to permission to parse arbitrary pages:

- `json-feed-v1` / `rss-atom-v1`: require `provider_contract_confirmed=true` for enabled acquisition;
- `structured-html-v1`: requires `structured_contract_confirmed=true` for enabled acquisition;
- Century Games, Discord, YouTube, Reddit, Facebook and Instagram require their existing explicit permission/API-access policy checks;
- X requires confirmed API access in addition to account identity and bearer credential.

Source credentials remain application/environment configuration and are never persisted in source policy.

## Trust boundary

`GiftCodeIngestionPage`, checkpoints, health state and provider request metadata describe acquisition only. Parsed observations continue through `IngestApprovedGiftCodeObservation`, append-only `GiftCodeProvenance`, the canonical trust resolver and evidence-gated fact reconciliation.

An installed adapter, successful HTTP response, valid checkpoint, research-catalogue entry or `verificationPassed=true` transport result cannot independently create official authority. Source policy and the canonical evidence model remain authoritative.

## Related documentation

- `docs/product/gift-code-source-acquisition-contract.md`
- `docs/operations/gift-code-source-acquisition.md`
- `docs/operations/gift-codes.md`
- `docs/reference/gift-codes.md`
- `docs/product/gift-code-researched-source-rollout.md`
