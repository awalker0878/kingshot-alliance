# Gift Code Redemption Workspace — Acceptance Matrix

Status: **Complete — verified current capability**
Verified candidate-adapter correction: `610082b9cbf663e3eb6bd0c14dbe3cdba1d2b086`

The prior source-ingestion closeout was reopened because its wording exceeded the installed adapter set. The correction is accepted because the missing production adapters are implemented, integrated with the canonical approved-source path, covered by end-to-end tests and green on all workflows applicable to this change.

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
- All additional source ingestion reuses approved-source policy, `RunApprovedGiftCodeSourceIngestion`, `IngestApprovedGiftCodeObservation` and append-only provenance.
- The installed candidate pull-adapter registry contains `json-feed-v1`, `rss-atom-v1` and `structured-html-v1`; signed source webhook ingestion is the fourth push transport, not a parallel adapter/trust engine.
- `json-feed-v1` accepts only bounded JSON observations from an approved canonical hostname/path.
- `rss-atom-v1` accepts bounded RSS/Atom documents and requires explicit direct-child Gift Code elements rather than inferring codes from prose or nested content markup.
- `structured-html-v1` accepts bounded approved HTML documents and requires explicit machine-readable `data-gift-code*` attributes rather than scraping arbitrary page text.
- Missing RSS/Atom or structured-HTML assertion metadata normalizes to canonical `available`; supported assertions remain `available`, `invalid`, `expires`, `reward`, and `applicability`.
- Pull adapters do not follow redirects, enforce source/document/observation bounds and preserve source/retrieval/parser versions plus content fingerprints/raw-evidence references.
- RSS/Atom rejects DTD/entity declarations and network XML access is disabled.
- Parser formats, policy failures and observation failures use the existing ingestion failure/quarantine diagnostics.
- End-to-end tests persist approved RSS/Atom and structured-HTML sources, execute the scheduled ingestion runner, and prove their observations enter canonical provenance and produce valid catalogue trust when qualified.
- Signed webhook ingestion validates active source status/policy, signature, timestamp/replay and bounded payload size before entering the same canonical observation path.
- Source transport or reputation never confers authority by itself; evidence qualification and current source policy remain authoritative.

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
- Source-adapter behavior tests cover registry installation, RSS, Atom, structured HTML, malicious XML rejection, explicit-markup boundaries, observation bounds and end-to-end canonical ingestion.
- Moderation HTTP coverage proves a Platform Administrator can register each installed pull adapter with canonical source policy.
- Desktop/mobile Playwright coverage includes create/resume/skip/result/trust-change scenarios and the platform Gift Code source-policy surface.
- Large fixtures maintain bounded query behavior for at least 100 Gift Codes, 20 Governors and 2,000 possible code/Governor pairs.
- Repository implementation contains no compatibility alias, parallel redemption engine or parallel source-ingestion trust path introduced by this correction.

## Verification evidence

Candidate `610082b9cbf663e3eb6bd0c14dbe3cdba1d2b086` passed all workflows applicable to the correction:

- CI — success, including full PHP/frontend checks, fresh-database installation, production image build, ephemeral staging deployment, backup/restore and image scan;
- Architecture V3 Verification — success, including route boot, architecture invariants, fresh schema, static analysis and the full V3 PHPUnit suite;
- Intelligence Verification — success;
- Visual Regression — success;
- CodeQL — success;
- Dependency Review — success.

`King Perks Verification` is path-filtered to King Perks-owned files and did not trigger for this correction because no such path changed. King Perks remains unchanged from the previously verified `main` baseline.

Primary Gift Code workspace acceptance coverage includes `GiftCodeWorkspaceV3Test`, `GiftCodeRedemptionWorkspaceV3Test`, `GiftCodeSessionAcceptanceV3Test`, `GiftCodeAllianceCoverageV3Test`, `GiftCodeSourceAdaptersV3Test`, `GiftCodeModerationHttpV3Test`, Platform Administration diagnostics behavior coverage, and `tests/v3/Visual/GiftCodes.spec.ts`.
