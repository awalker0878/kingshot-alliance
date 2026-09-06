# Gift Code source acquisition reference

Status: Current complete pre-deployment reference

This reference describes the source-acquisition, synchronization and advisory intelligence state surrounding the canonical Gift Code provenance/trust pipeline. It does not define a second trust engine.

## Source lifecycle and operational fields

`gift_code_sources` stores source identity, policy/activation controls and current operational health. Important fields include:

| Field | Meaning |
| --- | --- |
| `activation_status` | `registered`, `configured`, `enabled`, or `revoked` |
| `health_status` | Current provider/acquisition health independent of authority |
| `ingestion_enabled` | Master automated acquisition control |
| `push_enabled` | Provider push discovery control |
| `head_poll_enabled` | Freshness polling control |
| `reconciliation_enabled` | Completeness/reconciliation polling control |
| `backfill_enabled` | Historical backfill control |
| `authority_promotion_enabled` | Independent permission for qualified source evidence to participate in authority promotion |
| `next_eligible_ingestion_at` | Earliest scheduled retry after provider/backoff constraints |
| `last_ingestion_attempt_at` | Most recent attempted head acquisition |
| `last_ingestion_success_at` | Most recent successful acquisition |
| `last_ingestion_failure_at` | Most recent failed acquisition |
| `last_ingestion_failure_code` | Stable operational failure classification |
| `last_ingestion_error` | Bounded operator-facing failure detail |
| `last_provider_request_id` | Provider request/correlation identifier when exposed |
| `last_retrieval_version` | Provider/document retrieval version when exposed |
| `last_retry_after_seconds` | Last provider/runtime retry delay |
| `last_rate_limit_remaining` | Last observed provider rate-limit remainder |
| `last_quota_remaining` | Last observed provider quota remainder |
| `request_count` | Cumulative provider requests for this source |
| `observation_count` | Cumulative acquired observations |
| `accepted_observation_count` | Cumulative observations accepted at the canonical provenance boundary |
| `duplicate_observation_count` | Cumulative provenance duplicates |
| `quarantined_observation_count` | Cumulative observations or provider documents held for review |
| `reconciliation_gap_count` | Cumulative independently detected push/reconciliation gaps |
| `signature_failure_count` | Cumulative rejected push signatures |
| `replay_rejection_count` | Cumulative rejected push replays |
| `last_observation_at` | Most recent acquisition that produced an observation |
| `last_smoke_check_*` | Latest non-ingesting provider-readiness result and bounded diagnostics |

These fields are operational state. They do not grant evidence authority.

## Independent synchronization state

Provider continuation state no longer lives in one source-level ingestion cursor. `gift_code_source_sync_states` owns an independent row per source and synchronization mode.

The deployed modes are:

- `head` — freshness polling and current high-water state;
- `reconciliation` — independent completeness checking, especially for push-enabled sources;
- `backfill` — historical paging that can advance or restart without rewinding live freshness state.

Mode-specific state may include:

- `latest_observed_provider_id`;
- `committed_high_water` and `candidate_high_water`;
- `active_sync_since_id` and `active_page_token`;
- `backfill_page_token` and `backfill_boundary_provider_id`;
- conditional HTTP `http_etag` and `http_last_modified` values;
- `last_not_modified_at`, `last_head_poll_at`, `last_reconciliation_at`, and `last_backfill_at`;
- an optimistic `version` used when advancing durable state.

A provider page is processed before the corresponding synchronization state is committed. A failed or parser-drifted page cannot advance `committed_high_water`. Head, reconciliation and backfill therefore cannot silently overwrite one another's continuation state.

## Ingestion-run fields

`gift_code_ingestion_runs` records one bounded source run with:

- synchronization mode;
- source/result cursor diagnostics for that individual run;
- result checkpoint returned by the adapter;
- page/request counts;
- examined/accepted/duplicate/quarantined counts;
- provider request id and retrieval version;
- retry-after/rate-limit/quota metadata;
- stable failure code/message;
- started/completed timestamps.

Run cursor/checkpoint values are diagnostic receipts for that run. Durable continuation authority belongs to `gift_code_source_sync_states`.

## Activation readiness and smoke checks

`EvaluateGiftCodeSourceActivationReadiness` returns named checks including source activation, installed adapter, canonical domain, provider identity, credentials, permissions/provider contract, policy completeness and verification-boundary validity.

Before first acquisition activation, `SetGiftCodeSourceAcquisitionControls` additionally requires a recent passing non-ingesting smoke check. `RunGiftCodeSourceSmokeCheck` performs a tightly bounded provider read and persists only operational diagnostics; it deliberately does not write Gift Code provenance, trust/catalogue state or synchronization state.

Acquisition and authority promotion are separate controls. A passing smoke check can establish transport readiness but cannot establish source authority.

## Health values

Expected source health values include:

| Health | Meaning |
| --- | --- |
| `disabled` | Source is not ingesting |
| `pending` | Enabled but not successfully checked yet |
| `healthy` | Latest successful acquisition produced useful observations |
| `idle` | Latest successful acquisition produced no new observations |
| `rate_limited` | Provider throttled acquisition |
| `authentication_failed` | Credential rejected/expired |
| `permission_revoked` | Required provider permission is unavailable |
| `contract_changed` | Provider identity/shape no longer matches approved contract |
| `parser_failed` | Response violates the supported machine-readable parser contract |
| `degraded` | Other retrieval/provider failure or partial-quality degradation |

Parser/unsupported-format failures are quarantined for review and do not move the committed synchronization high-water. Transport/provider outages remain explicit source failures.

## Stable provider failure codes

The common provider response contract normalizes HTTP failures as follows:

- 401 -> `authentication_failed`;
- 403 -> `permission_revoked`;
- 404 -> `source_identity_unavailable`;
- 408 -> `provider_timeout`;
- 409 -> `provider_conflict`;
- 429 -> `rate_limited`;
- 5xx -> `provider_unavailable`;
- other non-success -> `source_retrieval_failed`.

Generic runtime fallbacks remain bounded, including adapter unavailable, unsupported source format, source-policy rejection, unsupported observation format, observation-policy rejection and observation-ingestion failure.

A failure code controls operations/diagnostics; canonical Gift Code status is still derived only from qualified provenance and moderation.

## Adapter continuation semantics

| Adapter family | Durable synchronization behavior |
| --- | --- |
| X API | Post-id high water; bounded pages are drained before the candidate high water is committed |
| Discord | Message-snowflake high water with independent reconciliation |
| YouTube | Provider paging tokens/checkpoints independently scoped to head/reconciliation/backfill |
| Reddit | Provider listing token/checkpoint independently scoped by mode |
| Facebook | Graph paging/checkpoint state independently scoped by mode |
| Instagram | Graph paging/checkpoint state independently scoped by mode |
| JSON feed | Provider cursor only when defined by the approved feed contract |
| RSS/Atom | Complete-document retrieval; no invented provider cursor |
| Structured HTML | Complete documented markup retrieval; no invented provider cursor |
| Century Games RSS | Complete approved feed retrieval with conditional HTTP state; no invented provider cursor |

The bounded acquirers reject repeated continuation tokens within a sweep so provider paging loops cannot run indefinitely.

## Push delivery and reconciliation

Push is an acquisition optimization, not a trust grant. YouTube WebSub, Facebook Page webhook, entitlement-gated X Filtered Stream and Discord Gateway paths authenticate a source-scoped delivery reservation before canonical retrieval/processing. Replay keys are durable and idempotent, terminal processing state cannot be reset by a duplicate delivery, and signature/replay failures remain visible in source health.

Push-enabled sources retain independent reconciliation. A missed push event can therefore become an explicit reconciliation gap instead of silently disappearing from acquisition history.

## Acquisition intelligence

`gift_code_observation_clusters` and `gift_code_source_performance_projections` are rebuildable advisory projections over append-only provenance and operational counters.

Observation clusters distinguish total observations from distinct/independent publishers and official sources. They may expose earliest observed source/publication time and Time-to-Code when the source publication timestamp is actually known. They do not claim causal copying or a true publication origin that the evidence cannot prove.

Source performance may expose observations, unique codes, first discoveries, qualified/correct/incorrect/conflicting observations, discovery and confirmation latency, median/P95 Time-to-Code, useful/quarantine/duplicate ratios and last productive observation time.

`GiftCodeAcquisitionEffectivenessQuery` composes the global acquisition view. `gift-codes:rebuild-acquisition-intelligence` rebuilds the advisory projections on a bounded hourly schedule and can also be invoked from the platform operations surface.

None of these metrics can replace `GiftCodeTrustResolver`, qualify evidence by themselves, auto-approve a source or increase `authority_promotion_enabled`.

## Operator surfaces

- `/platform/gift-codes/sources` owns registered-source identity/policy and push configuration.
- `/platform/gift-codes/sources/operations` exposes smoke diagnostics, activation/health, head/reconciliation/backfill actions, transport counters, Time-to-Code and source-performance projections.
- `/platform/gift-codes/sources/evidence-entry` provides the intentional non-scraping curator path for registered sources without a legitimate machine interface, including availability, invalidity, expiry, reward and applicability evidence.

Reward/applicability evidence remains subject to the normal evidence-qualification and conflict rules.

## Provider contract gates

Generic automation is not equivalent to permission to parse arbitrary pages:

- `json-feed-v1` / `rss-atom-v1` require `provider_contract_confirmed=true` for enabled acquisition;
- `structured-html-v1` requires `structured_contract_confirmed=true` and explicit supported machine markup;
- Century Games, Discord, YouTube, Reddit, Facebook and Instagram require their existing explicit permission/API-access policy checks;
- X requires confirmed API access in addition to account identity and bearer credential.

Source credentials remain application/environment configuration and are never persisted in source policy. Century Games remains fail-closed without express permission or a cooperative contract.

## Trust boundary

Adapter pages, synchronization state, ingestion runs, smoke checks, health metrics, correlation clusters and source-performance projections describe acquisition only. Parsed observations continue through `IngestApprovedGiftCodeObservation`, append-only `GiftCodeProvenance`, the canonical trust resolver and evidence-gated fact reconciliation.

An installed adapter, successful HTTP response, passing smoke check, valid checkpoint, research-catalogue entry, high source-performance score or `verificationPassed=true` transport result cannot independently create official authority.

## Verification contract

The dedicated `Gift Code Verification` workflow validates a fresh schema, Pint, Larastan, all GiftCodes V3 backend tests, shared pull-adapter conformance, shared push-transport conformance, provider failure/parser-drift fixtures, frontend lint/format/type contracts and a production frontend build. Repository-wide Architecture V3, Intelligence, Visual Regression, CodeQL, Dependency Review and CI gates remain applicable before merge.

## Related documentation

- `docs/product/gift-code-source-acquisition-contract.md`
- `docs/product/gift-code-research-backed-acquisition-plan.md`
- `docs/operations/gift-code-source-acquisition.md`
- `docs/operations/gift-codes.md`
- `docs/reference/gift-codes.md`
- `docs/product/gift-code-researched-source-rollout.md`
