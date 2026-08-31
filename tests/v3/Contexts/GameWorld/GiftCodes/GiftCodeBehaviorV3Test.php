<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Actions\BeginGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\IngestApprovedGiftCodeObservation;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\ModerateGiftCode;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptions;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeExpiryNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeTransitionNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeFacts;
use App\Contexts\GameWorld\GiftCodes\Actions\ReconcileGiftCodeSourcePolicyChanges;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordObservedGiftCodeRedemptionResult;
use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeRedemptionProvider;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceReconciliationJob;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeCatalogQuery;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use App\ReadModels\ProductionLaunch\ProductionLaunchReadiness;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use LogicException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;
use UnexpectedValueException;

final class GiftCodeBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_ordinary_submissions_cannot_claim_official_authority_and_provenance_is_append_only(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);

        try {
            app(SubmitGiftCode::class)->handle($player, [
                'code' => 'SPOOFED-OFFICIAL',
                'source_type' => 'official',
            ]);
            self::fail('Ordinary submissions must not be able to assert official authority.');
        } catch (ValidationException) {
            self::assertSame(0, GiftCode::query()->count());
        }

        $first = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'COMMUNITY-CODE',
            'source_type' => 'manual',
        ]);
        $duplicate = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'community-code',
            'source_type' => 'manual',
        ]);
        $additional = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'COMMUNITY-CODE',
            'source_type' => 'community',
            'source_url' => 'https://community.example.test/post/1',
        ]);

        self::assertFalse($first->duplicateDetected);
        self::assertTrue($duplicate->duplicateDetected);
        self::assertFalse($duplicate->provenanceAdded);
        self::assertTrue($additional->provenanceAdded);
        self::assertSame(2, GiftCodeProvenance::query()->count());
        self::assertSame(GiftCodeStatus::Pending, GiftCode::query()->firstOrFail()->status);

        $evidence = GiftCodeProvenance::query()->firstOrFail();
        $evidence->source_label = 'Rewritten evidence';
        $this->expectException(LogicException::class);
        $evidence->save();
    }

    public function test_approved_ingestion_is_idempotent_and_rejects_misleading_source_urls(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->source('official-one', 'official-one.example.test');
        $observation = $this->observation('TRUSTED-CODE', sourceUrl: 'https://official-one.example.test/gifts/1');

        $first = app(IngestApprovedGiftCodeObservation::class)->handle((string) $source->id, $observation);
        $replay = app(IngestApprovedGiftCodeObservation::class)->handle((string) $source->id, $observation);

        self::assertTrue($first['accepted']);
        self::assertFalse($first['duplicate']);
        self::assertTrue($replay['duplicate']);
        self::assertSame(1, GiftCodeProvenance::query()->count());
        $giftCode = GiftCode::query()->findOrFail($first['gift_code_id']);
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(1, $giftCode->status_revision);

        $this->expectException(ValidationException::class);
        app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $source->id,
            $this->observation('EVIL-CODE', sourceUrl: 'https://lookalike.example.test/gifts/2'),
        );
    }

    public function test_conflicting_expiry_stays_disputed_until_an_authorized_resolution_and_all_revisions_emit(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.moderation', true);
        $firstSource = $this->source('expiry-one', 'expiry-one.example.test');
        $secondSource = $this->source('expiry-two', 'expiry-two.example.test');
        $firstExpiry = now()->addDays(2)->startOfHour()->toIso8601String();
        $secondExpiry = now()->addDays(3)->startOfHour()->toIso8601String();

        $first = app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $firstSource->id,
            $this->observation(
                'EXPIRY-CONFLICT',
                sourceUrl: 'https://expiry-one.example.test/gift',
                claimedExpiresAt: $firstExpiry,
                contentFingerprint: hash('sha256', 'expiry-one'),
            ),
        );
        $giftCode = GiftCode::query()->findOrFail($first['gift_code_id']);
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(1, $giftCode->status_revision);
        self::assertSame(1, $giftCode->expires_revision);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $secondSource->id,
            $this->observation(
                'EXPIRY-CONFLICT',
                sourceUrl: 'https://expiry-two.example.test/gift',
                claimedExpiresAt: $secondExpiry,
                contentFingerprint: hash('sha256', 'expiry-two'),
            ),
        );
        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Disputed, $giftCode->status);
        self::assertSame('credible_expiry_conflict', $giftCode->status_reason_code);
        self::assertSame(2, $giftCode->status_revision);
        self::assertSame(2, $giftCode->expires_revision);

        $actor = $this->administrator();
        app(ModerateGiftCode::class)->handle(
            $actor,
            (string) $giftCode->id,
            GiftCodeModerationAction::CorrectExpiry,
            'Resolve the two qualified publication dates.',
            [],
            null,
            ['expires_at' => $secondExpiry, 'expiry_precision' => 'hour'],
        );
        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(3, $giftCode->status_revision);
        self::assertSame(3, $giftCode->expires_revision);

        $statusRevisions = OutboxMessage::query()
            ->where('event_type', 'gift_code.status_changed')
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->get()
            ->map(static fn (OutboxMessage $message): int => (int) ($message->payload['status_revision'] ?? -1))
            ->all();
        self::assertSame([1, 2, 3], $statusRevisions);
    }

    public function test_multi_governor_handoff_is_owner_scoped_and_outcomes_do_not_invent_global_applicability(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $firstGovernor = $scenarios->player($account->userId, 1501, 'GOV-1501-A');
        $secondGovernor = $scenarios->player($account->userId, 1502, 'GOV-1502-B');
        $foreignGovernor = $scenarios->player($other->userId, 1503, 'GOV-1503-C');
        $source = $this->source('redemption-source', 'redemption.example.test');
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $source->id,
            $this->observation('MULTI-GOVERNOR', sourceUrl: 'https://redemption.example.test/gift'),
        );
        $actor = User::query()->findOrFail($account->userId);

        $prepared = app(PrepareGiftCodeRedemptions::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            [$firstGovernor->playerId, $secondGovernor->playerId, $foreignGovernor->playerId],
        )->toArray();

        self::assertSame(2, $prepared['succeeded']);
        self::assertSame(1, $prepared['failed']);
        self::assertSame([$foreignGovernor->playerId], $prepared['failedItemIds']);
        self::assertSame(2, GiftCodeRedemption::query()->count());

        app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            $firstGovernor->playerId,
            'wrong_kingdom',
        );
        $completed = app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            $secondGovernor->playerId,
            'redeemed',
        );
        $terminalReplay = app(RecordObservedGiftCodeRedemptionResult::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            $secondGovernor->playerId,
            'invalid',
        );

        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $completed->status);
        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $terminalReplay->status);
        self::assertSame($completed->attempts, $terminalReplay->attempts);

        DB::table('players')->where('id', $firstGovernor->playerId)->update(['user_id' => $other->userId]);
        try {
            app(RecordObservedGiftCodeRedemptionResult::class)->handle(
                $actor,
                $ingested['gift_code_id'],
                $firstGovernor->playerId,
                'rate_limited',
            );
            self::fail('Result recording must reauthorize current Governor ownership.');
        } catch (ValidationException) {
            self::assertSame(
                GiftCodeRedemptionStatus::WrongKingdom,
                GiftCodeRedemption::query()
                    ->where('gift_code_id', $ingested['gift_code_id'])
                    ->where('player_id', $firstGovernor->playerId)
                    ->firstOrFail()
                    ->status,
            );
        }

        self::assertSame(GiftCodeStatus::Valid, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);
        self::assertDatabaseMissing('gift_code_fact_projections', [
            'gift_code_id' => $ingested['gift_code_id'],
            'fact_type' => 'applicability',
            'qualified' => true,
        ]);
    }

    public function test_only_currently_valid_codes_can_start_a_governor_handoff(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId, 1504, 'GOV-1504-D');
        $actor = User::query()->findOrFail($account->userId);

        foreach ([GiftCodeStatus::Pending, GiftCodeStatus::Disputed] as $status) {
            $giftCode = GiftCode::query()->create([
                'code' => 'UNAVAILABLE-'.$status->value,
                'normalized_code' => 'UNAVAILABLE-'.mb_strtoupper($status->value),
                'status' => $status,
                'status_revision' => 1,
                'status_reason_code' => 'test_'.$status->value,
                'status_evidence_ids' => [],
                'status_changed_at' => now(),
                'status_derived_at' => now(),
                'discovered_at' => now(),
                'expires_revision' => 0,
            ]);

            $prepared = app(PrepareGiftCodeRedemptions::class)->handle(
                $actor,
                (string) $giftCode->id,
                [$governor->playerId],
            )->toArray();

            self::assertSame(0, $prepared['succeeded']);
            self::assertSame(1, $prepared['failed']);
            $redemption = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCode->id)
                ->where('player_id', $governor->playerId)
                ->firstOrFail();
            self::assertSame(GiftCodeRedemptionStatus::Expired, $redemption->status);
            self::assertSame('code_unavailable', $redemption->last_result_code);
            self::assertNull($redemption->redemption_url);
        }
    }

    public function test_handoff_rechecks_current_trust_state_after_the_provider_returns(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId, 1505, 'GOV-1505-E');
        $giftCode = GiftCode::query()->create([
            'code' => 'CONCURRENT-TRUST-CHANGE',
            'normalized_code' => 'CONCURRENT-TRUST-CHANGE',
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);
        $provider = new class((string) $giftCode->id) implements GiftCodeRedemptionProvider {
            public function __construct(private readonly string $giftCodeId) {}

            public function name(): string
            {
                return 'concurrency-test';
            }

            public function begin(GiftCodeReference $giftCode, PlayerReference $player): GiftCodeRedemptionOutcome
            {
                GiftCode::query()->whereKey($this->giftCodeId)->update([
                    'status' => GiftCodeStatus::Invalid->value,
                    'status_reason_code' => 'platform_rejected',
                    'status_changed_at' => now(),
                    'status_derived_at' => now(),
                ]);

                return new GiftCodeRedemptionOutcome(
                    GiftCodeRedemptionStatus::AwaitingConfirmation,
                    'official_handoff',
                    'Continue in the official Gift Code Center.',
                    'https://example.test/gift-center',
                );
            }
        };
        $begin = new BeginGiftCodeRedemption(
            $provider,
            app(RecordGiftCodeRedemptionOutcome::class),
        );

        $result = $begin->handle((string) $giftCode->id, $governor);

        self::assertSame(GiftCodeRedemptionStatus::Expired, $result->status);
        self::assertNull($result->redemptionUrl);
        self::assertDatabaseHas('gift_code_redemptions', [
            'gift_code_id' => (string) $giftCode->id,
            'player_id' => $governor->playerId,
            'status' => GiftCodeRedemptionStatus::Expired->value,
            'last_result_code' => 'code_unavailable',
            'redemption_url' => null,
        ]);
    }

    public function test_reward_and_applicability_are_promoted_only_from_qualified_non_conflicting_evidence(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $firstSource = $this->source('facts-one', 'facts-one.example.test');
        $secondSource = $this->source('facts-two', 'facts-two.example.test');

        $created = app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $firstSource->id,
            $this->observation('FACT-CODE', sourceUrl: 'https://facts-one.example.test/gift'),
        );
        app(ReconcileGiftCodeFacts::class)->handle($created['gift_code_id']);
        $unknownReward = GiftCodeFactProjection::query()
            ->where('gift_code_id', $created['gift_code_id'])
            ->where('fact_type', 'reward')
            ->firstOrFail();
        self::assertFalse($unknownReward->qualified);
        self::assertSame('reward_details_unknown', $unknownReward->reason_code);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $firstSource->id,
            $this->observation(
                'FACT-CODE',
                assertion: 'reward',
                assertionPayload: ['items' => [['name' => 'Wood', 'quantity' => 1000]]],
                sourceUrl: 'https://facts-one.example.test/gift',
                contentFingerprint: hash('sha256', 'reward-one'),
            ),
        );
        $reward = $unknownReward->fresh();
        self::assertTrue($reward?->qualified ?? false);
        self::assertSame('Wood', $reward?->value['items'][0]['name'] ?? null);

        app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $firstSource->id,
            $this->observation(
                'FACT-CODE',
                assertion: 'applicability',
                assertionPayload: ['kingdoms' => [1501]],
                sourceUrl: 'https://facts-one.example.test/gift',
                contentFingerprint: hash('sha256', 'applicability-one'),
            ),
        );
        app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $secondSource->id,
            $this->observation(
                'FACT-CODE',
                assertion: 'applicability',
                assertionPayload: ['kingdoms' => [1502]],
                sourceUrl: 'https://facts-two.example.test/gift',
                contentFingerprint: hash('sha256', 'applicability-two'),
            ),
        );
        $applicability = GiftCodeFactProjection::query()
            ->where('gift_code_id', $created['gift_code_id'])
            ->where('fact_type', 'applicability')
            ->firstOrFail();
        self::assertFalse($applicability->qualified);
        self::assertSame('credible_applicability_conflict', $applicability->reason_code);
    }

    public function test_notification_fanout_respects_preferences_and_revisions(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.notification_fanout', true);
        config()->set('game_world.gift_codes.moderation', true);
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId, 1601, 'GOV-1601-A');
        $secondGovernor = $scenarios->player($account->userId, 1602, 'GOV-1602-B');
        $source = $this->source('notification-source', 'notifications.example.test');
        $expiry = now()->addHours(12)->startOfMinute()->toIso8601String();

        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $governor->playerId,
            'gift_code.available',
            DeliveryChannel::InApp,
            false,
        );
        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $governor->playerId,
            'gift_code.expiring',
            DeliveryChannel::InApp,
            false,
        );
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $source->id,
            $this->observation(
                'NOTIFY-CODE',
                sourceUrl: 'https://notifications.example.test/gift',
                claimedExpiresAt: $expiry,
            ),
        );

        $availability = app(QueueGiftCodeTransitionNotifications::class)->handle();
        self::assertSame(1, $availability->createdDeliveryCount);
        $availabilityDelivery = NotificationDelivery::query()
            ->where('notification_type', 'gift_code.available')
            ->firstOrFail();
        self::assertNull($availabilityDelivery->player_id);
        self::assertCount(2, $availabilityDelivery->metadata['governors'] ?? []);

        app(PrepareGiftCodeRedemptions::class)->handle(
            User::query()->findOrFail($account->userId),
            $ingested['gift_code_id'],
            [$governor->playerId],
        );
        self::assertSame(0, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);

        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $governor->playerId,
            'gift_code.expiring',
            DeliveryChannel::InApp,
            true,
        );
        self::assertSame(1, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);
        self::assertSame(0, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);

        $actor = $this->administrator();
        app(ModerateGiftCode::class)->handle(
            $actor,
            $ingested['gift_code_id'],
            GiftCodeModerationAction::CorrectExpiry,
            'Qualified source clarified the expiry minute.',
            [],
            null,
            ['expires_at' => now()->addHours(10)->startOfMinute()->toIso8601String(), 'expiry_precision' => 'minute'],
        );
        self::assertSame(1, app(QueueGiftCodeExpiryNotifications::class)->handle()->createdDeliveryCount);
        self::assertSame(2, NotificationDelivery::query()->where('notification_type', 'gift_code.expiring')->count());
    }

    public function test_installed_json_feed_adapter_retrieves_a_bounded_verified_source_page(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $actor = $this->administrator();
        $sourceId = app(ManageGiftCodeSourceRegistry::class)->register($actor, [
            'source_key' => 'publisher-feed',
            'name' => 'Publisher feed',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_json_feed',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'auto_verify' => true,
                'feed_path' => '/gift-codes.json',
            ],
            'ingestion_enabled' => true,
        ]);
        Http::fake([
            'publisher.example.test/gift-codes.json*' => Http::response([
                'version' => 'feed-42',
                'next_cursor' => 'next-page',
                'items' => [[
                    'code' => 'JSON-FEED-CODE',
                    'assertion' => 'available',
                    'source_url' => 'https://publisher.example.test/gifts/json-feed-code',
                    'published_at' => now()->subMinute()->toIso8601String(),
                    'version' => 'publication-7',
                ]],
            ], 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'ETag' => '"feed-42"',
            ]),
        ]);

        $sweep = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: 'publisher-feed');

        self::assertSame(1, $sweep->accepted);
        self::assertSame('next-page', GiftCodeSourceRegistry::query()->findOrFail($sourceId)->ingestion_cursor);
        $evidence = GiftCodeProvenance::query()->firstOrFail();
        self::assertSame('publication-7', $evidence->source_version);
        self::assertSame('ETag:"feed-42"', $evidence->retrieval_version);
        self::assertSame(JsonFeedGiftCodeSourceAdapter::KEY, $evidence->parser_version);
        self::assertSame(GiftCodeEvidenceVerificationState::Verified, $evidence->verification_state);
        Http::assertSent(static fn ($request): bool => $request->url() === 'https://publisher.example.test/gift-codes.json?limit=100');
    }

    public function test_moderation_rejects_a_stale_trust_revision(): void
    {
        config()->set('game_world.gift_codes.moderation', true);
        $actor = $this->administrator();
        $giftCode = GiftCode::query()->create([
            'code' => 'STALE-REVIEW',
            'normalized_code' => 'STALE-REVIEW',
            'status' => GiftCodeStatus::Pending,
            'status_revision' => 2,
            'status_reason_code' => 'awaiting_verified_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);

        try {
            app(ModerateGiftCode::class)->handle(
                $actor,
                (string) $giftCode->id,
                GiftCodeModerationAction::Verify,
                expectedStatusRevision: 1,
            );
            self::fail('A stale moderation decision must be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('expected_status_revision', $exception->errors());
        }

        self::assertSame(0, $giftCode->moderationDecisions()->count());
    }

    public function test_launch_readiness_requires_flags_and_an_enabled_source_with_an_installed_adapter(): void
    {
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertFalse($checks->get('gift_code_feature_flags')['passed'] ?? true);

        config()->set('game_world.gift_codes.moderation', true);
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.notification_fanout', true);
        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertTrue($checks->get('gift_code_feature_flags')['passed'] ?? false);
        self::assertFalse($checks->get('gift_code_ingestion_sources')['passed'] ?? true);

        GiftCodeSourceRegistry::query()->create([
            'source_key' => 'launch-ready-feed',
            'name' => 'Launch ready feed',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'is_active' => true,
            'verification_method' => 'approved_json_feed',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'policy_revision' => 1,
            'provenance_policy' => ['auto_verify' => true, 'feed_path' => '/gift-codes.json'],
            'ingestion_enabled' => true,
        ]);

        $checks = collect(app(ProductionLaunchReadiness::class)->checks())->keyBy('key');
        self::assertTrue($checks->get('gift_code_ingestion_sources')['passed'] ?? false);
    }

    public function test_ingestion_runner_is_bounded_idempotent_and_records_parser_failure_health(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.ingestion_batch_size', 10);
        $source = $this->source('adapter-source', 'adapter.example.test', 'stable-adapter');
        $observation = $this->observation('ADAPTER-CODE', sourceUrl: 'https://adapter.example.test/gift');
        $stable = new class($observation) implements GiftCodeSourceAdapter {
            public function __construct(private readonly GiftCodeIngestionObservation $observation) {}

            public function key(): string
            {
                return 'stable-adapter';
            }

            public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
            {
                return new GiftCodeIngestionPage([$this->observation], 'cursor-1');
            }
        };
        $broken = new class implements GiftCodeSourceAdapter {
            public function key(): string
            {
                return 'broken-adapter';
            }

            public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
            {
                throw new UnexpectedValueException('The parser rejected an unsupported source format.');
            }
        };
        $quarantinedObservation = $this->observation(
            'QUARANTINED-OBSERVATION',
            sourceUrl: 'https://misleading.example.test/gift',
        );
        $quarantining = new class($quarantinedObservation) implements GiftCodeSourceAdapter {
            public function __construct(private readonly GiftCodeIngestionObservation $observation) {}

            public function key(): string
            {
                return 'quarantining-adapter';
            }

            public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
            {
                return new GiftCodeIngestionPage([$this->observation], null);
            }
        };
        $runner = new RunApprovedGiftCodeSourceIngestion(
            new GiftCodeSourceAdapterRegistry([$stable, $broken, $quarantining]),
            app(IngestApprovedGiftCodeObservation::class),
        );

        $first = $runner->handle(sourceKey: $source->source_key);
        $replay = $runner->handle(sourceKey: $source->source_key);
        self::assertSame(1, $first->accepted);
        self::assertSame(1, $replay->duplicates);
        self::assertSame(1, GiftCodeProvenance::query()->count());

        $quarantineSource = $this->source(
            'quarantined-source',
            'quarantined.example.test',
            'quarantining-adapter',
        );
        $quarantine = $runner->handle(sourceKey: $quarantineSource->source_key);
        self::assertSame(1, $quarantine->quarantined);
        self::assertSame(0, $quarantine->failedSources);
        $quarantineRun = GiftCodeIngestionRun::query()
            ->where('gift_code_source_id', $quarantineSource->id)
            ->latest('started_at')
            ->firstOrFail();
        self::assertSame('completed_with_quarantine', $quarantineRun->status);
        self::assertSame('observation_policy_rejected', $quarantineRun->failure_code);
        self::assertStringContainsString('QUARANTINED-OBSERVATION', (string) $quarantineRun->failure_message);
        self::assertStringContainsString('evidence://gift-code/', (string) $quarantineRun->failure_message);

        $failedSource = $this->source('broken-source', 'broken.example.test', 'broken-adapter');
        $failure = $runner->handle(sourceKey: $failedSource->source_key);
        self::assertSame(1, $failure->quarantined);
        self::assertSame(0, $failure->failedSources);
        $parserFailureRun = GiftCodeIngestionRun::query()
            ->where('gift_code_source_id', $failedSource->id)
            ->latest('started_at')
            ->firstOrFail();
        self::assertSame('completed_with_quarantine', $parserFailureRun->status);
        self::assertSame(1, $parserFailureRun->quarantined_count);
        self::assertSame('unsupported_source_format', $parserFailureRun->failure_code);
    }

    public function test_revoked_source_is_reconciled_without_rewriting_its_evidence(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->source('revocable-source', 'revocable.example.test');
        $ingested = app(IngestApprovedGiftCodeObservation::class)->handle(
            (string) $source->id,
            $this->observation('REVOKED-CODE', sourceUrl: 'https://revocable.example.test/gift'),
        );
        self::assertSame(GiftCodeStatus::Valid, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);

        $actor = $this->administrator();
        app(ManageGiftCodeSourceRegistry::class)
            ->revoke($actor, (string) $source->id, 'The source lost its platform approval.');
        self::assertSame(1, GiftCodeSourceReconciliationJob::query()->count());

        $result = app(ReconcileGiftCodeSourcePolicyChanges::class)->handle();
        self::assertTrue($result['completed']);
        self::assertSame(GiftCodeStatus::Pending, GiftCode::query()->findOrFail($ingested['gift_code_id'])->status);
        self::assertSame(1, GiftCodeProvenance::query()->count());
    }

    public function test_alliance_authority_does_not_grant_platform_source_authority(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $scenarios->alliance($player);
        $identity = app(AccountIdentityQuery::class)->require($account->userId);

        $this->expectException(AuthorizationException::class);
        app(ManageGiftCodeSourceRegistry::class)->register($identity, [
            'source_key' => 'alliance-claimed-source',
            'name' => 'Alliance claimed source',
            'classification' => 'official',
            'canonical_domain' => 'alliance.example.test',
            'verification_method' => 'manual_review',
            'ingestion_enabled' => false,
        ]);
    }

    public function test_catalogue_pagination_and_detail_history_keep_constant_query_budgets(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $governor = $scenarios->player($account->userId);
        $detailCode = null;

        for ($index = 0; $index < 80; $index++) {
            $giftCode = GiftCode::query()->create([
                'code' => sprintf('BUDGET-%03d', $index),
                'normalized_code' => sprintf('BUDGET-%03d', $index),
                'status' => GiftCodeStatus::Valid,
                'status_revision' => 1,
                'status_reason_code' => 'qualified_positive_evidence',
                'status_evidence_ids' => [],
                'status_changed_at' => now(),
                'status_derived_at' => now(),
                'discovered_at' => now()->subSeconds($index),
                'expires_revision' => 0,
            ]);
            $detailCode ??= $giftCode;
        }
        self::assertInstanceOf(GiftCode::class, $detailCode);

        for ($index = 0; $index < 120; $index++) {
            GiftCodeProvenance::query()->create([
                'gift_code_id' => (string) $detailCode->id,
                'submitted_by_player_id' => $governor->playerId,
                'source_type' => GiftCodeSource::Community,
                'source_label' => 'History '.$index,
                'assertion' => 'available',
                'evidence_classification' => GiftCodeEvidenceClassification::CommunityClaim,
                'verification_state' => GiftCodeEvidenceVerificationState::Unverified,
                'observed_at' => now()->subSeconds($index),
                'fingerprint' => hash('sha256', 'history-'.$index),
            ]);
        }

        $catalogue = app(GiftCodeCatalogQuery::class);
        DB::flushQueryLog();
        DB::enableQueryLog();
        $page = $catalogue->pageForPlayers([$governor->playerId], ['view' => 'active'], 25);
        $indexQueries = count(DB::getQueryLog());
        self::assertCount(25, $page->items());
        self::assertNotNull($page->nextCursor());
        self::assertLessThanOrEqual(4, $indexQueries);

        DB::flushQueryLog();
        $detail = $catalogue->detailForPlayers((string) $detailCode->id, [$governor->playerId]);
        $detailQueries = count(DB::getQueryLog());
        DB::disableQueryLog();
        self::assertCount(120, $detail->provenances);
        self::assertLessThanOrEqual(7, $detailQueries);
    }

    private function source(string $key, string $domain, ?string $adapterKey = null): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => str_replace('-', ' ', ucfirst($key)),
            'classification' => 'official',
            'canonical_domain' => $domain,
            'is_active' => true,
            'verification_method' => 'signed_publication',
            'adapter_key' => $adapterKey,
            'policy_revision' => 1,
            'provenance_policy' => ['auto_verify' => true],
            'ingestion_enabled' => true,
        ]);
    }

    /** @param array<string,mixed>|null $assertionPayload */
    private function observation(
        string $code,
        string $assertion = 'available',
        ?array $assertionPayload = null,
        ?string $sourceUrl = null,
        ?string $claimedExpiresAt = null,
        ?string $contentFingerprint = null,
        bool $verificationPassed = true,
    ): GiftCodeIngestionObservation {
        return new GiftCodeIngestionObservation(
            code: $code,
            assertion: $assertion,
            assertionPayload: $assertionPayload,
            sourceUrl: $sourceUrl,
            claimedExpiresAt: $claimedExpiresAt,
            expiryPrecision: $claimedExpiresAt === null ? null : 'hour',
            expiryTimezone: $claimedExpiresAt === null ? null : 'UTC',
            publishedAt: now()->subMinute()->toIso8601String(),
            sourceVersion: 'source-v1',
            retrievalVersion: 'retrieval-v1',
            parserVersion: 'parser-v1',
            contentFingerprint: $contentFingerprint ?? hash('sha256', implode('|', [
                $code,
                $assertion,
                json_encode($assertionPayload, JSON_THROW_ON_ERROR),
                $claimedExpiresAt ?? '',
            ])),
            rawEvidenceRef: 'evidence://gift-code/'.strtolower($code).'/'.$assertion,
            verificationPassed: $verificationPassed,
        );
    }

    private function administrator(): AccountIdentity
    {
        $account = app(ScenarioFactory::class)->account();
        User::query()->whereKey($account->userId)->update([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        app(ManagePlatformAdministrator::class)->grant($account->userId);

        return app(AccountIdentityQuery::class)->require($account->userId);
    }
}
