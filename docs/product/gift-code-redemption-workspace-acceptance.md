# Gift Code Redemption Workspace — Acceptance Matrix

Status: **Complete — verified current capability**
Verified implementation candidate: `caf75e732a71ea5dfdd91f7c6432c30fa689d828`

The extension is accepted because the following behavior is implemented and exercised by automated tests.

## Workspace and personal state

- Account state is unique per account/Gift Code and never duplicates canonical trust/reward/expiry values.
- Pin, snooze, dismiss, restore and reminder changes are account-authorized and idempotent.
- Personal state changes never mutate global Gift Code status/revisions/facts.
- Workspace sections are bounded and derived from current canonical Gift Code and owned-Governor state.

## Session creation

- Supports one code/one Governor, one code/many Governors, many codes/one Governor and many codes/many Governors.
- Duplicate selectors do not create duplicate items.
- Foreign/revoked Governor selectors are rejected or excluded after current ownership resolution.
- Terminal success is excluded.
- Invalid, expired, disputed and quarantined codes are unavailable.
- Retryable results are included only when retry is due.
- Missing in-game Player ID is unavailable with a stable reason.
- Qualified applicability evidence is honored; statistical intelligence is not used as canonical eligibility.
- Item/session size is bounded.

## Session execution

- Session belongs to the authenticated account.
- Every item action rechecks account ownership and current catalogue state.
- Session survives refresh and can resume on another device.
- Skip, abandon and completion are explicit states.
- `valid -> disputed/quarantined/invalid/expired` makes uncompleted items unavailable.
- Result recording writes the existing per-Governor redemption ledger and synchronizes session state.
- Terminal success remains immutable.

## Communications

- GiftCodes submits logical `NotificationIntent` only.
- Reminder and consolidated actionable notifications are idempotent.
- Current recipient preference, endpoint, Governor ownership, quiet hours and digest cadence remain Communications-owned.
- GiftCodes does not persist provider endpoints/email addresses or implement provider retry.

## Redemption intelligence

- Requires configured minimum sample and distinct-account thresholds.
- One account with many Governors does not satisfy the distinct-account threshold alone.
- Rolling windows exclude stale observations.
- Aggregates expose no account IDs, Player IDs or Governor names.
- Intelligence never mutates canonical trust, expiry, reward or applicability.

## Rewards and sources

- Structured reward rendering uses only qualified fact projections.
- Unknown/conflicting reward evidence remains unknown/conflicted.
- Additional source ingestion reuses approved-source policy and append-only provenance.
- Signed webhook ingestion validates source status, policy, signature/timestamp/replay and bounded payload size.

## Alliance coverage

- Members see only their account-owned Governors by default.
- Aggregate Alliance coverage requires the explicit `gift_codes.coverage` permission, delegated through the `Gift Code Coordinator` specialist role; R4/R5 rank alone does not grant coverage.
- Coverage returns aggregate counts only and does not disclose individual member redemption history.
- Alliance rank/coverage permission never grants platform Gift Code moderation or approved-source authority.
- Membership and permission changes are rechecked on every coverage request.

## Contributor quality

- Projection is derived from existing provenance/moderation evidence.
- Reputation may affect moderation priority/spam controls only.
- Reputation never upgrades community evidence to official evidence.

## Quality gates

- Fresh-schema installation succeeds with no compatibility/backfill migration path.
- PHP/static-analysis/architecture/localization/accessibility/TypeScript gates pass.
- Desktop/mobile Playwright coverage includes create/resume/skip/result/trust-change scenarios.
- Large fixtures maintain bounded query behavior for at least 100 Gift Codes, 20 Governors and 2,000 possible code/Governor pairs.
- Repository implementation contains no compatibility alias or parallel redemption engine introduced by this extension.

## Verification evidence

Implementation candidate `caf75e732a71ea5dfdd91f7c6432c30fa689d828` passed all required repository workflows:

- CI, including full PHP/frontend checks, fresh-database installation, production image build, ephemeral staging deployment, backup/restore and image scan;
- Architecture V3 Verification, including route boot, architecture invariants, fresh schema, static analysis and the full V3 PHPUnit suite;
- Intelligence Verification;
- Visual Regression, including desktop/mobile Playwright Gift Code workspace execution and trust-change scenarios;
- CodeQL;
- Dependency Review;
- King Perks Verification.

Primary Gift Code workspace acceptance coverage includes `GiftCodeWorkspaceV3Test`, `GiftCodeRedemptionWorkspaceV3Test`, `GiftCodeSessionAcceptanceV3Test`, `GiftCodeAllianceCoverageV3Test`, Platform Administration diagnostics behavior coverage, and `tests/v3/Visual/GiftCodes.spec.ts`.