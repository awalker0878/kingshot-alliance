# Gift Code Redemption Workspace — Acceptance Matrix

Status: Active selected-extension acceptance matrix

The extension is acceptable only when the following behavior is implemented and exercised by automated tests.

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
- Authorized Alliance leadership receives aggregate coverage only by default.
- Individual follow-up requires an explicit existing Alliance permission.
- Alliance rank never grants platform Gift Code moderation/source authority.
- Membership/permission changes are rechecked on every request/reminder action.

## Contributor quality

- Projection is derived from existing provenance/moderation evidence.
- Reputation may affect moderation priority/spam controls only.
- Reputation never upgrades community evidence to official evidence.

## Quality gates

- Fresh-schema installation succeeds with no compatibility/backfill migration path.
- PHP/static-analysis/architecture/localization/accessibility/TypeScript gates pass.
- Desktop/mobile Playwright coverage includes create/resume/skip/result/trust-change scenarios.
- Large fixtures maintain bounded query behavior for at least 100 Gift Codes, 20 Governors and 2,000 possible code/Governor pairs.
- Repository search finds no compatibility aliases or parallel redemption engine introduced by this extension.
