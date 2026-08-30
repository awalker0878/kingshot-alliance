# Gift Codes

`GameWorld/GiftCodes` owns global Gift Code identity, approved source policy, append-only provenance, canonical trust/expiry/fact projections, moderation decisions, ingestion health/cursors, notification eligibility campaigns, and per-Player/per-Kingdom redemption state.

## Invariants

- `gift_codes` stores catalogue identity and derived state, never raw source authority.
- `GiftCodeProvenance` is append-only. Corrections append evidence or moderation decisions.
- `GiftCodeTrustResolver` is the only resolver. Material trust and expiry changes advance monotonic revisions.
- Manual/community submissions are unverified and cannot select registered/official authority.
- Platform source policy controls authoritative ingestion; canonical-domain and adapter-policy checks run server-side.
- Moderation requires verified email, MFA, recent password confirmation at HTTP entry, and an active Platform Administrator or Gift Code curator grant. Source/curator administration remains Platform Administrator-only.
- Redemption is unique per Gift Code and Player. Target Player IDs are selectors; `PlayerReferenceQuery` re-resolves current account ownership.
- Negative Governor observations require a prior official-site handoff. Terminal success is immutable and retries are bounded.
- Reward/applicability projections remain unknown or conflicted until the evidence gate passes.
- Communications owns delivery state/preferences; GiftCodes owns campaign eligibility and revision-aware idempotency inputs.

## Primary flows

`SubmitGiftCode` appends ordinary evidence. `IngestApprovedGiftCodeObservation` appends registered-source evidence. Both reconcile canonical trust; ingestion and moderation also reconcile reward/applicability facts.

`PrepareGiftCodeRedemptions` resolves each owned Governor independently and delegates to the configured `GiftCodeRedemptionProvider`. The default `OfficialGiftCodeHandoff` opens Century Games' center. `RecordObservedGiftCodeRedemptionResult` maps the supported UI vocabulary into the per-Governor ledger.

`ModerateGiftCode` records one decision and invokes the same trust/fact resolvers. `ManageGiftCodeSourceRegistry` revisions or revokes source policy and schedules `ReconcileGiftCodeSourcePolicyChanges`; provenance is never mutated.

`ScheduleGiftCodeNotificationCampaign` persists availability/trust transitions. `QueueGiftCodeTransitionNotifications` and `QueueGiftCodeExpiryNotifications` perform bounded, idempotent fan-out through `NotificationDeliveryService` after current-state rechecks.

`RunApprovedGiftCodeSourceIngestion` calls only installed, tagged `GiftCodeSourceAdapter` implementations, persists per-source run health and quarantines observation-level failures. No undocumented Century Games redemption automation belongs in this context.

## Operational entry points

- Web catalogue/detail/redemption: `routes/gift-codes.php`.
- Platform moderation/source/curator operations: `/platform/gift-codes` in the same route file.
- Canonical read API: `GET /api/v1/gift-codes`.
- Schedulers and replayable commands: `gift-codes:maintain`, `gift-codes:ingest-approved-sources`, and `gift-codes:reconcile-source-policies`.
- Operational flags: `gift_codes.moderation`, `gift_codes.approved_source_ingestion`, and `gift_codes.notification_fanout`.

The authoritative product/security contract is `docs/product/gift-code-extension-program.md`; architecture rationale is ADR-0004.
