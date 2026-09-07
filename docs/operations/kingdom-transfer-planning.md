# Kingdom Transfer Planning operations

Status: Current — 2026-09-07

Owner: `GameWorld/KingdomTransfers`

Product contract: [`../product/kingdom-transfer-planning.md`](../product/kingdom-transfer-planning.md)  
Source matrix: [`../product/kingdom-transfer-official-rules-source-matrix.md`](../product/kingdom-transfer-official-rules-source-matrix.md)

## Operational intent

Kingdom Transfer Planning is evidence-sensitive and fail-closed. Support must repair underlying sourced facts or planning commitments, never edit a derived eligibility result.

Prefer **Needs verification** over optimistic eligibility whenever a material source is missing, stale, conflicting or non-authoritative.

## Diagnostic order

For an eligibility or capacity complaint, identify Alliance, Plan, Transfer Window, participant and target Kingdom, then inspect:

1. window boundaries/current phase;
2. official Transfer Group membership/revision;
3. target condition: Power Cap, classification, Hero Generation, Truegold and age threshold;
4. latest authoritative target capacity observation;
5. participant observations: Power, Hero Generation, Truegold, age-over-target, cooldown, target character count, passes, invitation, resource protection and final in-game verification;
6. freshness/conflict selection state;
7. capacity reservation and invitation allocation state;
8. structured eligibility requirements/primary action;
9. independent Alliance readiness/blockers;
10. Evidence review/receipt when a fact came through Screenshot Intake.

Readiness, planning allocations and Evidence provenance do not independently prove game eligibility.

## Current capacity diagnostics

Always distinguish:

- **Observed KingShot capacity** — current sourced usage/inventory;
- **Official capacity limits** — classification-derived constants from `TransferOfficialRulebook`;
- **Alliance planning reservations/allocations** — internal intent only;
- **Projected remaining** — observed remaining minus relevant current Alliance commitments.

Ordinary target limits are 55 total / 35 Ordinary Invite / 20 Transfer Opens. Leading target limits are 30 / 20 / 10. Special Invite inventory is observed and bounded to a maximum of three.

If projected capacity appears too low after completed transfers, compare the finalized commitment `updated_at` with the newest capacity `observed_at`. Finalized commitments stop subtracting once a newer/equal authoritative observation supersedes them. Planned/reserved future intent continues subtracting.

Do not manually release a confirmed commitment merely to make capacity green; refresh the authoritative in-game capacity observation.

## Completion/withdrawal reconciliation

Withdrawal atomically releases consuming slot reservations and cancels consuming invitation allocations.

Completion atomically finalizes consuming slot reservations and invitation allocations along with the existing roster/Player transfer outcome. These finalized planning states are not substitutes for observed game truth.

If a completion transaction fails, none of the completion/finalization changes should survive. Retry the canonical completion Action; do not repair rows manually.

## Observation correction

Governor observations, target conditions and capacity observations are append-only. Correct a mistake by recording a newer sourced observation/correction. Do not overwrite historical rows.

Official Transfer Group correction creates a new revision and supersedes the previous current revision.

This historical chain is necessary to explain assessment changes.

## Freshness and conflicts

Mutable participant facts require explicit `valid_until` when used as current eligibility truth.

- expired → `stale`;
- simultaneous authoritative disagreement → `conflicting`;
- absent/non-authoritative → `unknown`.

Any material stale/conflict/unknown state prevents `eligible_now`.

Do not extend a validity boundary just to force a favorable result.

## Transfer Pass support boundary

Do not calculate required Transfer Passes from Transfer Score. The current required count is an observed in-game fact and must remain in the supported official 1–50 range. An out-of-range observation is treated as conflicting and must be rechecked.

## Storehouse/resource warning

`resource_protection_verified=false` means the Governor has a known pre-transfer resource-loss action to resolve. It is not modeled as a hard game prohibition.

Missing/stale/conflicting resource verification still blocks an optimistic `eligible_now` result because resources above Storehouse protection are a material transfer consequence.

## Special Invite incidents

A consuming Special Invite planning allocation requires:

- target classification = authoritative Ordinary;
- current Special Invite inventory;
- remaining inventory after other current Alliance allocations.

Leading Kingdoms cannot consume a Special Invite allocation. If the target classification or inventory is missing, refresh the game fact instead of overriding the planning check.

Observed participant `invitation_status` remains separate from Alliance allocation state.

## Evidence support

`Intelligence/Evidence` owns screenshots/reviews and KingdomTransfers owns accepted game facts.

Five explicit families are supported:

- Governor status;
- Transfer Score & Passes;
- invitation;
- target Kingdom rules;
- official Transfer Group.

Target Kingdom rules are schema v2. Fixture-proven reviewed fields may include target number, Power Cap, classification, Hero Generation, Truegold and character-age threshold days.

No schema may infer or create `in_game_rules_verified=true`.

### Evidence retry/idempotency

- exact/visual/semantic duplicate handling is Evidence-owned;
- owner destination replay uses a stable `transfer_evidence_receipts.idempotency_key`;
- retrying the same approved review returns the existing receipt and cannot append duplicate owner truth;
- material Plan/window/target drift requires re-review.

Score/pass commit is atomic across Transfer Score, available passes, required passes and receipt.

## Evidence review changes

Before commit, a correction creates a new immutable Evidence review revision. After commit, accepted domain truth is corrected through the relevant KingdomTransfers owner Action/append-only observation.

Deleting/redacting Evidence never deletes already accepted KingdomTransfers history.

## Authorization incidents

Every mutation must re-resolve current actor/Alliance authority and concrete owner scope. Foreign Alliance/Plan/window/participant/Evidence identifiers must not disclose existence.

Manual forms cannot select `source_type=evidence`.

## Audit/outbox

Material events include:

- Transfer Window/group/condition/capacity changes;
- participant observation changes;
- capacity reservation/invitation allocation changes;
- readiness/withdrawal/completion changes;
- accepted Evidence receipts.

Completion metadata includes finalized reservation/allocation counts; withdrawal metadata includes released/cancelled counts.

Do not log raw screenshots/OCR, unrestricted Governor values, secret tokens or private free-form evidence payloads.

## Metrics

Privacy-safe useful telemetry includes:

- eligibility outcome counts;
- requirement state/failure counts;
- observed/projected capacity pressure;
- Special Invite availability/shortage;
- stale/conflicting fact frequency;
- Evidence replay/failure/duplicate rates;
- rejected cross-scope writes;
- transfer read query-budget regressions.

## Backup and restore

Database backup/restore must include:

- `transfer_windows`;
- official `transfer_groups` / membership;
- `transfer_kingdom_condition_observations`;
- `transfer_kingdom_capacity_observations`;
- `transfer_plans`;
- `transfer_participants`;
- `transfer_cohorts`;
- `transfer_observations`;
- `transfer_capacity_reservations`;
- `transfer_invitation_allocations`;
- `transfer_evidence_receipts`;
- readiness transitions/blockers/completions;
- related audit/outbox rows.

After restore, recompute eligibility/projections from owner inputs. There is no eligibility cache/boolean to restore.

Verify at least one Evidence replay returns its existing destination receipt without duplicating owner history.

## Release verification

Before release/merge readiness:

1. fresh PostgreSQL migration succeeds;
2. KingdomTransfers migrations/constraints install cleanly;
3. Pint and PHPStan pass;
4. KingdomTransfers V3 behavior/contract/completeness tests pass;
5. Evidence target-rules v2 classification/extraction/review/preview/commit tests pass;
6. frontend lint/Prettier/type/build pass;
7. readiness/manage UX is responsive, localized and keyboard-accessible;
8. observed versus planned capacity is visually distinct;
9. Architecture V3 and Intelligence Verification pass;
10. Visual Regression passes on deterministic desktop/mobile states;
11. CodeQL and Dependency Review pass;
12. authorization/isolation, concurrency/idempotency and query-budget coverage pass;
13. documentation/source matrix/reference/operations contracts agree with code.

No compatibility shim, legacy alias, dual read/write or migration-backfill path is required for this fresh deployment.
