# Kingdom Transfer Planning operations

Status: Current — 2026-08-26

Owner: `GameWorld/KingdomTransfers`

## Operational intent

Kingdom Transfer Planning is an evidence-sensitive planning capability. Operations must prefer an explicit **Needs verification** result over optimistic eligibility when a source is missing, stale, conflicting or no longer valid.

Do not repair eligibility by editing derived output. Repair the underlying sourced Transfer Window/group/condition/observation and allow the deterministic evaluator to recompute the assessment.

Screenshot Intake: Transfer Evidence is an authorized source-ingestion path into this owner context. `Intelligence/Evidence` owns the screenshot and review provenance; KingdomTransfers owns every accepted observation, target condition, official group, validity/conflict rule and eligibility result.

## Support diagnostics

For a reported eligibility problem, identify the Alliance, Transfer Plan, Transfer Window, participant and target Kingdom, then inspect in this order:

1. Transfer Window boundaries and current official phase;
2. current official Transfer Group revisions and source/target Kingdom membership;
3. current target Kingdom condition observation, including Power Cap/classification provenance;
4. participant Governor observations for Power, Transfer Score, Transfer Passes, invitation status and in-game rule verification;
5. selected observation freshness/conflict state;
6. structured eligibility requirements and `evaluated_at`;
7. independent workflow readiness and manual blockers;
8. when a fact came from Screenshot Intake, the Evidence destination receipt and source Evidence/review identifiers visible to the authorized operator.

A manual planning blocker does not prove a game rule failed, and workflow readiness does not prove a Governor is game-eligible. An Evidence screenshot also proves only the fields defined by its schema; it does not prove unrelated game rules.

## Observation correction

Governor observations are append-only. Do not overwrite prior Power, Transfer Score, Transfer Pass, invitation or in-game verification records to correct a mistake. Record a new sourced observation with the correct observation/validity boundary.

Target Kingdom condition corrections likewise preserve history. Official Transfer Group corrections create a new revision and supersede the previous current revision.

This historical chain is required to explain why an eligibility assessment changed.

Evidence review correction and owner correction are separate:

- before commit, a reviewer creates a newer immutable Evidence review revision;
- after commit, correcting accepted game/domain truth uses the relevant KingdomTransfers owner write and preserves the accepted historical record;
- deleting/redacting the screenshot never edits accepted Transfer history.

## Idempotency and retries

Repeated ingestion or a user retry with the same normalized observation fingerprint must return/reuse the existing observation instead of inserting a duplicate.

For Screenshot Intake, owner observation fingerprints remain an internal protection while a separate `transfer_evidence_receipts.idempotency_key` protects the entire schema handoff. Replaying the same approved Evidence review must return the existing destination receipt and must not append duplicate observation/condition/group history.

When a write fails before commit, retry the same request. When the request may have committed but the client did not receive the response, use the normal Evidence commit path rather than manually entering replacement facts. The stable destination idempotency key is specifically designed for the crash window after the owner transaction commits but before Evidence records acknowledgement.

Do not build dual-write or compensating legacy paths for the pre-rename `TransferGroup` planning concept. The supported planning concept is `TransferCohort` only.

## Atomic score/pass recovery

A reviewed Transfer Score/pass screenshot owns three related facts: Transfer Score, passes available and observed passes required. `RecordTransferScorePassEvidence` commits all three observations plus the destination receipt in one KingdomTransfers transaction.

Support expectations:

- validation or persistence failure in any one of the three values leaves none of the three new observations and no receipt;
- retrying after a successful owner commit returns the same receipt;
- never repair a partial-looking client result by manually creating the missing observations—verify the owner transaction/receipt first.

## Stale observations

Mutable Governor facts carry `valid_until`. Once that boundary passes, the observation may remain visible as history but cannot satisfy current eligibility.

Expected support outcome:

- UI identifies the stale requirement;
- source/reference and `observed_at` remain visible;
- assessment is `needs_verification` when the stale fact is material;
- operator/officer records a new observation rather than extending the old row in place.

Do not extend validity merely to force a green eligibility result.

Transfer Evidence does not manufacture a validity boundary. Governor-status, score/pass and invitation review require the reviewer to provide the explicit validity boundary used by the owner. Upload time is not silently substituted for observation time or validity.

## Conflicting observations

When multiple current authoritative observations disagree for the same material fact/target, the selected requirement is conflicting and eligibility is `needs_verification`.

Resolve by determining which source is authoritative/current and recording a correction or newer observation according to the owning write contract. Preserve the conflicting records for audit/history.

The Evidence preview is advisory derived state. It uses the same owner evaluator, but it cannot erase an existing conflict and it cannot supply facts absent from the active screenshot schema.

## Transfer Evidence scope-change incidents

A Transfer Evidence review snapshots scalar Plan/participant/Transfer Window/target meaning. The destination owner compares that approved snapshot with current state before a new write.

If commit returns a scope-changed/re-review validation error:

1. confirm the participant still belongs to the expected Plan;
2. confirm the Plan still references the reviewed Transfer Window;
3. confirm participant direction and target Kingdom;
4. determine whether the retained screenshot still proves the current target meaning;
5. create a new review revision only when the evidence is still applicable; otherwise capture newer in-game evidence.

Never edit review scope IDs or destination owner IDs to force the handoff through.

## Evidence references

An `evidence_id` is a reference to `Intelligence/Evidence`-owned source material, not ownership transfer. Every supplied Evidence identifier is validated through the owner contract for the same Alliance; `source_type=evidence` additionally requires an approved relevant Evidence review. Support tooling must not expose or dereference evidence across an unauthorized Alliance, Plan, participant or Player scope.

Transfer Screenshot Intake owner Actions receive only scalar Evidence/review IDs and typed values. They do not import Evidence Eloquent models. The Evidence lookup contract confirms same-Alliance/approved provenance while Evidence remains the persistence owner of the source/review.

If Evidence is later redacted for retention/privacy, accepted KingdomTransfers history and the destination receipt remain. Authorized support may see a safe Evidence reference/tombstone even when the binary is no longer retained.

## Screenshot schema support boundaries

The five supported v1 screenshot classes are:

- Governor status → Governor Power only;
- Transfer Score/pass screen → displayed Transfer Score, displayed available Passes, displayed required Passes;
- invitation screen → supported invitation enum and current target reconciliation;
- target Kingdom rules → visible target Kingdom, Power Cap and fixture-proven classification;
- official Transfer Group → complete explicitly visible group label/membership.

Operationally important exclusions:

- required Passes are not calculated from Transfer Score;
- none of these schemas can create `in_game_rules_verified=true`;
- hidden/off-screen official-group membership is not inferred;
- ambiguous invitation wording remains unverified;
- visually similar screenshots are not automatically treated as semantic duplicates.

If a game UI changes so a fixture no longer proves a field reliably, treat the screenshot as unsupported/needs review until the schema/fixture corpus is intentionally versioned. Do not loosen extraction heuristics in production diagnostics.

## Semantic duplicates versus destination retries

These are intentionally separate support cases.

**Semantic duplicate:** two Evidence reviews describe the same reviewed game state in the same schema/scope. Evidence blocks the newer review until an authorized manager records an explicit supported resolution.

**Destination replay:** the same immutable approved review is retried because acknowledgement was interrupted. KingdomTransfers returns the existing receipt under the same idempotency key.

A genuinely newer observation should have a newer observation boundary and/or changed reviewed meaning and should not be suppressed as the old game state.

## Audit and outbox

Material transfer writes emit audit/outbox records for operational traceability, including window/group/condition/observation changes, planning workflow mutations and accepted Transfer Evidence receipts. Audit metadata should identify owner scope, record identifiers, source/schema/action and timing needed to diagnose a change without unnecessarily duplicating raw Governor values.

Evidence emits its own upload/classification/extraction/review/duplicate/commit/retry/redaction events. The cross-context correlation is Evidence/review/receipt identity; raw OCR text or screenshot contents do not belong in owner audit metadata.

Do not place raw screenshots, OCR/provider payloads, secret tokens, hashes or unrestricted private evidence values in audit/outbox metadata.

## Metrics and logs

Useful aggregate telemetry includes counts/rates for:

- `eligible_now`, `blocked`, `eligible_with_action`, and `needs_verification` assessments;
- stale/unknown/conflicting requirement frequency by requirement key;
- observation write retries/deduplications;
- Transfer Evidence destination replay count/failure rate by schema Action;
- Evidence semantic/visual/exact duplicate rates from privacy-safe aggregate diagnostics;
- rejected cross-scope or invalid observation/Evidence writes;
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
- `transfer_evidence_receipts`;
- readiness transitions/manual blockers/completions;
- associated audit/outbox rows covered by the platform backup policy.

Evidence-owned source/review/commit tables are covered by the application database backup and Evidence binary-store policy documented in `docs/operations/screenshot-intake.md`.

Restore verification should confirm that observation history, official group revisions and Transfer Evidence receipts remain intact and that a known participant assessment can be recomputed from restored inputs. No derived eligibility cache/boolean is required for recovery.

For one restored committed Evidence handoff, retry the Evidence commit path and confirm the existing destination receipt is returned without appending owner history.

## Release verification

Before release:

1. run a fresh PostgreSQL migration, including the narrow Evidence scope constraint and Transfer review/receipt tables;
2. verify all five schema fixture corpora, classifier/extractor negative cases and field whitelists;
3. verify Transfer Evidence authorization/scope-change behavior, atomic score/pass commit and crash/idempotent replay;
4. verify evaluator/window/observation authorization and existing idempotency tests;
5. run strict PHPStan/Pint and frontend lint/format/type/build;
6. run architecture/contract/documentation checks, including no Transfer OCR context/generic schema and no foreign Evidence/Transfer Eloquent crossing;
7. run keyboard/accessibility/localization checks on the participant Evidence workflow;
8. run deterministic eligible/blocked/needs-verification visual scenarios on desktop/mobile, including the Evidence panel state;
9. run CodeQL, dependency review, Intelligence Verification and container/security checks;
10. run staging smoke checks plus backup/restore/replay verification.

A release is not acceptable while a documented Transfer Evidence schema, fixture family, destination Action, participant UX state, security boundary, test or delivery-ledger item remains partial or unverified.