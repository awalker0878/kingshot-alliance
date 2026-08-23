# Kingdom Transfer Planning operations

Status: Current — 2026-08-23

Owner: `GameWorld/KingdomTransfers`

## Operational intent

Kingdom Transfer Planning is an evidence-sensitive planning capability. Operations must prefer an explicit **Needs verification** result over optimistic eligibility when a source is missing, stale, conflicting or no longer valid.

Do not repair eligibility by editing derived output. Repair the underlying sourced Transfer Window/group/condition/observation and allow the deterministic evaluator to recompute the assessment.

## Support diagnostics

For a reported eligibility problem, identify the Alliance, Transfer Plan, Transfer Window, participant and target Kingdom, then inspect in this order:

1. Transfer Window boundaries and current official phase;
2. current official Transfer Group revisions and source/target Kingdom membership;
3. current target Kingdom condition observation, including Power Cap/classification provenance;
4. participant Governor observations for Power, Transfer Score, Transfer Passes, invitation status and in-game rule verification;
5. selected observation freshness/conflict state;
6. structured eligibility requirements and `evaluated_at`;
7. independent workflow readiness and manual blockers.

A manual planning blocker does not prove a game rule failed, and workflow readiness does not prove a Governor is game-eligible.

## Observation correction

Governor observations are append-only. Do not overwrite prior Power, Transfer Score, Transfer Pass, invitation or in-game verification records to correct a mistake. Record a new sourced observation with the correct observation/validity boundary.

Target Kingdom condition corrections likewise preserve history. Official Transfer Group corrections create a new revision and supersede the previous current revision.

This historical chain is required to explain why an eligibility assessment changed.

## Idempotency and retries

Repeated ingestion or a user retry with the same normalized observation fingerprint must return/reuse the existing observation instead of inserting a duplicate.

When a write fails before commit, retry the same request. When the request may have committed but the client did not receive the response, query the participant/window history before manually entering a replacement. Duplicate-safe fingerprints make identical observation retries safe.

Do not build dual-write or compensating legacy paths for the pre-rename `TransferGroup` planning concept. The supported planning concept is `TransferCohort` only.

## Stale observations

Mutable Governor facts carry `valid_until`. Once that boundary passes, the observation may remain visible as history but cannot satisfy current eligibility.

Expected support outcome:

- UI identifies the stale requirement;
- source/reference and `observed_at` remain visible;
- assessment is `needs_verification` when the stale fact is material;
- operator/officer records a new observation rather than extending the old row in place.

Do not extend validity merely to force a green eligibility result.

## Conflicting observations

When multiple current authoritative observations disagree for the same material fact/target, the selected requirement is conflicting and eligibility is `needs_verification`.

Resolve by determining which source is authoritative/current and recording a correction or newer observation according to the owning write contract. Preserve the conflicting records for audit/history.

## Evidence references

An `evidence_id` is a reference to Intelligence/Evidence-owned source material, not ownership transfer. Support tooling must not expose or dereference evidence across an unauthorized Alliance or Player scope. If evidence is unavailable to the current operator, the transfer UI may retain a safe reference/status without exposing the artifact content.

## Audit and outbox

Material transfer writes emit audit/outbox records for operational traceability, including window/group/condition/observation changes and planning workflow mutations. Audit metadata should identify owner scope, record identifiers, kind/source and timing needed to diagnose a change without unnecessarily duplicating raw Governor values.

Do not place raw screenshots, secret tokens or unrestricted evidence payloads in audit/outbox metadata.

## Metrics and logs

Useful aggregate telemetry includes counts/rates for:

- `eligible_now`, `blocked`, `eligible_with_action`, and `needs_verification` assessments;
- stale/unknown/conflicting requirement frequency by requirement key;
- observation write retries/deduplications;
- rejected cross-scope or invalid observation writes;
- query-budget regressions on transfer read pages.

Telemetry must not require raw Governor Power/Score/Pass values when an outcome/requirement key is sufficient.

## Backup and restore

The standard application database backup/restore path must include:

- `transfer_windows`;
- official `transfer_groups` and `transfer_group_kingdoms`;
- `transfer_kingdom_condition_observations`;
- `transfer_plans`;
- `transfer_participants`;
- `transfer_cohorts`;
- `transfer_observations`;
- readiness transitions/manual blockers/completions;
- associated audit/outbox rows covered by the platform backup policy.

Restore verification should confirm that observation history and official group revisions remain intact and that a known participant assessment can be recomputed from restored inputs. No derived eligibility cache/boolean is required for recovery.

## Release verification

Before release:

1. run a fresh PostgreSQL migration;
2. verify evaluator/window/observation authorization and idempotency tests;
3. run strict PHPStan/Pint and frontend lint/format/type/build;
4. run architecture/contract/documentation checks;
5. run keyboard/accessibility/localization checks;
6. run deterministic eligible/blocked/needs-verification visual scenarios on desktop/mobile;
7. run CodeQL, dependency review and container scanning;
8. run staging smoke checks plus backup/restore verification.

A release is not acceptable while a product-contract delivery-ledger item is incomplete except an explicitly documented evidence-gated rule that would otherwise require inventing KingShot truth.
