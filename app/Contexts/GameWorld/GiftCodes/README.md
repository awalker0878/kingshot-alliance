# Gift Codes

`GameWorld/GiftCodes` owns global Gift Code identity, approved source policy, append-only provenance, canonical trust/expiry/fact projections, moderation decisions, ingestion health/cursors, notification eligibility campaigns, account-personal Gift Code workflow state, persistent redemption sessions, contributor projections, privacy-gated redemption signals, and per-Player/per-Kingdom redemption state.

## Invariants

- `gift_codes` stores catalogue identity and derived state, never raw source authority.
- `GiftCodeProvenance` is append-only. Corrections append evidence or moderation decisions.
- `GiftCodeTrustResolver` is the only resolver. Material trust and expiry changes advance monotonic revisions.
- Manual/community submissions are unverified and cannot select registered/official authority.
- Platform source policy controls authoritative ingestion; canonical-domain and adapter-policy checks run server-side.
- Moderation requires verified email, MFA, recent password confirmation at HTTP entry, and an active Platform Administrator or Gift Code curator grant. Source/curator administration remains Platform Administrator-only.
- Redemption is unique per Gift Code and Player. Target Player IDs are selectors; `PlayerReferenceQuery` re-resolves current account ownership.
- Personal pin/snooze/dismiss/reminder state is account workflow state only and never changes global catalogue trust, expiry, reward, applicability or evidence.
- Redemption-session items orchestrate work only. `gift_code_redemptions` remains authoritative for observed Governor redemption state, and every session action rechecks current account ownership and canonical Gift Code eligibility.
- Negative Governor observations require a prior official-site handoff. Terminal success is immutable and retries are bounded.
- Reward/applicability projections remain unknown or conflicted until the evidence gate passes.
- Redemption intelligence is privacy-gated observational aggregation. It never independently establishes canonical validity, invalidity, expiry or applicability.
- Alliance Gift Code coverage is an authorized aggregate projection and does not confer platform moderation authority or expose individual member redemption history.
- Contributor projections may prioritize workflow/moderation but never elevate community evidence to registered/official authority.
- Communications owns delivery state/preferences/endpoints/quiet-hours/digest/retry policy; GiftCodes owns notification meaning, eligibility and source idempotency inputs.

## Primary flows

`SubmitGiftCode` appends ordinary evidence. `IngestApprovedGiftCodeObservation` appends registered-source evidence. Both reconcile canonical trust; ingestion and moderation also reconcile reward/applicability facts. Signed source webhook ingestion is only an authenticated transport into the same approved-source observation path and never bypasses source policy or evidence verification.

`PrepareGiftCodeRedemptions` remains the single-code guided entry point. `CreateGiftCodeRedemptionSession` builds bounded many-code/many-Governor runs from server-resolved actionable pairs. `PrepareGiftCodeRedemptionSessionItem`, `RecordGiftCodeRedemptionSessionItemResult`, `SkipGiftCodeRedemptionSessionItem`, `ReconcileGiftCodeRedemptionSession` and `AbandonGiftCodeRedemptionSession` persist/resume workflow while delegating actual handoff/result truth to the canonical redemption path. The default `OfficialGiftCodeHandoff` opens Century Games' center; no undocumented redemption endpoint belongs here.

`UpdateGiftCodeAccountState` owns personal pin/snooze/dismiss/reminder state. `GiftCodeWorkspaceQuery` composes the account's bounded New/Ready/Expiring/Retry/In-progress/Snoozed/Completed views from current catalogue truth, current owned Governors, personal state and canonical redemptions.

`ModerateGiftCode` records one decision and invokes the same trust/fact resolvers. `ManageGiftCodeSourceRegistry` revisions or revokes source policy and schedules `ReconcileGiftCodeSourcePolicyChanges`; provenance is never mutated.

`ScheduleGiftCodeNotificationCampaign` persists catalogue lifecycle transitions. `QueueGiftCodeTransitionNotifications`, `QueueGiftCodeExpiryNotifications`, `QueueGiftCodeWorkspaceNotifications` and `QueueDueGiftCodeReminders` submit bounded idempotent `NotificationIntent` data through `NotificationDeliveryService`; Communications resolves actual destinations, quiet hours, digests and provider retry.

`GiftCodeRedemptionSignalService` exposes only threshold-qualified recent aggregate outcome observations. `GiftCodeRewardPresenter` renders qualified structured reward projections while preserving explicit unknown/conflict states. `RebuildGiftCodeContributorProjections` derives bounded contributor history without creating source authority.

`RunApprovedGiftCodeSourceIngestion` calls only installed, tagged `GiftCodeSourceAdapter` implementations, persists bounded per-source run health and quarantines parser/unsupported-format and observation-policy failures with reviewable diagnostics. No undocumented Century Games redemption automation belongs in this context.

## Operational entry points

- Web catalogue/detail/single-code redemption: `/gift-codes` via `routes/gift-codes.php`.
- Personal workspace/session execution: `/gift-codes/workspace` and nested session routes.
- Authorized Alliance aggregate coverage: `/gift-codes/workspace/alliance/{alliance}/coverage`.
- Platform moderation/source/curator operations: `/platform/gift-codes`.
- Canonical read API: `GET /api/v1/gift-codes`.
- Approved signed source ingestion: the internal Gift Code source webhook route in `routes/api.php`, gated by registered source policy and `gift_codes.source_webhook_ingestion`.
- Schedulers and replayable commands include `gift-codes:maintain`, `gift-codes:ingest-approved-sources`, `gift-codes:reconcile-source-policies`, workspace notification/reminder scheduling, and contributor projection maintenance.
- Existing operational flags remain `gift_codes.moderation`, `gift_codes.approved_source_ingestion`, and `gift_codes.notification_fanout`.
- Workspace extension flags are `gift_codes.redemption_workspace`, `gift_codes.redemption_intelligence`, `gift_codes.alliance_coverage`, `gift_codes.contributor_reputation`, and `gift_codes.source_webhook_ingestion`.

The canonical trust/security contract remains `docs/product/gift-code-extension-program.md`; the workspace extension contract is `docs/product/gift-code-redemption-workspace.md` with its acceptance matrix and delivery ledger. Architecture rationale for catalogue evidence remains ADR-0004.
