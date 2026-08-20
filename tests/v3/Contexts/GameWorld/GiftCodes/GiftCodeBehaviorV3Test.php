<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\BeginGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\ConfirmGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\ExpireGiftCodes;
use App\Contexts\GameWorld\GiftCodes\Actions\QueueGiftCodeExpiryNotifications;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\Actions\ReportGiftCodeIssue;
use App\Contexts\GameWorld\GiftCodes\Actions\PrepareGiftCodeRedemptions;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_submission_is_idempotent_and_redemption_is_scoped_to_a_governor(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 1123, 'GOV-1123-A');

        $first = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'Kingshot888',
            'source_type' => 'official',
            'source_label' => 'Century Games',
        ]);
        $second = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'KINGSHOT888',
            'source_type' => 'official',
            'source_label' => 'Century Games',
        ]);

        self::assertSame($first->giftCode->giftCodeId, $second->giftCode->giftCodeId);
        self::assertFalse($first->duplicateDetected);
        self::assertTrue($second->duplicateDetected);
        self::assertFalse($second->provenanceAdded);
        self::assertSame(1, GiftCodeProvenance::query()->count());

        $redemption = app(BeginGiftCodeRedemption::class)->handle($first->giftCode->giftCodeId, $player);
        self::assertSame(GiftCodeRedemptionStatus::AwaitingConfirmation, $redemption->status);
        self::assertSame('GOV-1123-A', $player->gamePlayerId);
        self::assertNotNull($redemption->redemptionUrl);

        $again = app(BeginGiftCodeRedemption::class)->handle($first->giftCode->giftCodeId, $player);
        self::assertSame($redemption->redemptionId, $again->redemptionId);
        self::assertSame(2, $again->attempts);
        self::assertSame(1, GiftCodeRedemption::query()->count());

        $confirmed = app(ConfirmGiftCodeRedemption::class)->handle($first->giftCode->giftCodeId, $player);
        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $confirmed->status);
        self::assertNotNull($confirmed->redeemedAt);
        self::assertSame(
            GiftCodeStatus::Valid,
            GiftCode::query()->findOrFail($first->giftCode->giftCodeId)->status,
        );

        $afterSuccess = app(BeginGiftCodeRedemption::class)->handle($first->giftCode->giftCodeId, $player);
        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $afterSuccess->status);
        self::assertSame(2, $afterSuccess->attempts);
    }

    public function test_retryable_outcomes_receive_a_bounded_retry_time(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 1198, 'GOV-1198-B');
        $giftCode = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'RETRY-ME',
            'source_type' => 'community',
        ]);

        $redemption = app(RecordGiftCodeRedemptionOutcome::class)->handle(
            $giftCode->giftCode->giftCodeId,
            $player,
            'test_provider',
            new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::RateLimited,
                '40019',
                'Provider rate limit reached.',
            ),
        );

        self::assertSame(GiftCodeRedemptionStatus::RateLimited, $redemption->status);
        $nextAttemptAt = $redemption->nextAttemptAt;
        self::assertNotNull($nextAttemptAt);
        self::assertTrue($nextAttemptAt->isFuture());

        $blockedRetry = app(RecordGiftCodeRedemptionOutcome::class)->handle(
            $giftCode->giftCode->giftCodeId,
            $player,
            'test_provider',
            new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::RateLimited,
                '40019',
                'Provider rate limit reached.',
            ),
        );
        self::assertSame(1, $blockedRetry->attempts);
        self::assertTrue($blockedRetry->nextAttemptAt?->equalTo($nextAttemptAt) ?? false);
    }

    public function test_provenance_is_append_only_and_conflicting_governor_evidence_is_disputed(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $firstPlayer = $scenarios->player($account->userId, 1201, 'GOV-1201-A');
        $secondPlayer = $scenarios->player($account->userId, 1201, 'GOV-1201-B');
        $first = app(SubmitGiftCode::class)->handle($firstPlayer, [
            'code' => 'EVIDENCE-ONE',
            'source_type' => 'official',
            'source_label' => 'Official post',
            'source_url' => 'https://example.test/official',
        ]);
        $second = app(SubmitGiftCode::class)->handle($secondPlayer, [
            'code' => 'evidence-one',
            'source_type' => 'community',
            'source_label' => 'Community confirmation',
            'source_url' => 'https://example.test/community',
        ]);

        self::assertTrue($second->duplicateDetected);
        self::assertTrue($second->provenanceAdded);
        self::assertSame(2, GiftCodeProvenance::query()->count());
        $giftCode = GiftCode::query()->findOrFail($first->giftCode->giftCodeId);
        self::assertSame('Official post', $giftCode->source_label);

        app(BeginGiftCodeRedemption::class)->handle((string) $giftCode->id, $firstPlayer);
        app(ConfirmGiftCodeRedemption::class)->handle((string) $giftCode->id, $firstPlayer);
        app(ReportGiftCodeIssue::class)->handle((string) $giftCode->id, $secondPlayer, 'invalid');

        self::assertSame(GiftCodeStatus::Disputed, $giftCode->fresh()?->status);
    }

    public function test_expiry_maintenance_is_idempotent_and_notifies_in_progress_governors(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId, 1202, 'GOV-1202-A');
        $submission = app(SubmitGiftCode::class)->handle($player, [
            'code' => 'EXPIRES-SOON',
            'source_type' => 'official',
        ]);
        $giftCode = GiftCode::query()->findOrFail($submission->giftCode->giftCodeId);
        $giftCode->forceFill(['expires_at' => now()->addHours(12)])->save();
        app(BeginGiftCodeRedemption::class)->handle((string) $giftCode->id, $player);

        self::assertSame(1, app(QueueGiftCodeExpiryNotifications::class)->handle());
        self::assertSame(0, app(QueueGiftCodeExpiryNotifications::class)->handle());
        self::assertSame(
            1,
            NotificationDelivery::query()
                ->where('notification_type', 'gift_code.expiring')
                ->where('player_id', $player->playerId)
                ->count(),
        );

        $giftCode->forceFill(['expires_at' => now()->subMinute()])->save();
        self::assertSame(1, app(ExpireGiftCodes::class)->handle());
        self::assertSame(GiftCodeStatus::Expired, $giftCode->fresh()?->status);
    }

    public function test_redemption_preparation_is_owner_scoped_and_returns_per_governor_results(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $other = $scenarios->account();
        $first = $scenarios->player($account->userId, 1203, 'GOV-1203-A');
        $second = $scenarios->player($account->userId, 1203, 'GOV-1203-B');
        $foreign = $scenarios->player($other->userId, 1204, 'GOV-1204-A');
        $submission = app(SubmitGiftCode::class)->handle($first, [
            'code' => 'OWNER-SCOPED',
            'source_type' => 'official',
        ]);
        $actor = User::query()->findOrFail($account->userId);

        $result = app(PrepareGiftCodeRedemptions::class)->handle(
            $actor,
            $account->userId,
            $submission->giftCode->giftCodeId,
            [$first->playerId, $second->playerId, $foreign->playerId],
        )->toArray();

        self::assertSame(2, $result['succeeded']);
        self::assertSame(1, $result['failed']);
        self::assertSame(0, $result['skipped']);
        self::assertSame([$foreign->playerId], $result['failedItemIds']);
        self::assertSame(2, GiftCodeRedemption::query()->count());
    }
}
