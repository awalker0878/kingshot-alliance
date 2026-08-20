<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\GameWorld\GiftCodes\Actions\BeginGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\ConfirmGiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\Actions\SubmitGiftCode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
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

        self::assertSame($first->giftCodeId, $second->giftCodeId);

        $redemption = app(BeginGiftCodeRedemption::class)->handle($first->giftCodeId, $player);
        self::assertSame(GiftCodeRedemptionStatus::AwaitingConfirmation, $redemption->status);
        self::assertSame('GOV-1123-A', $player->gamePlayerId);
        self::assertNotNull($redemption->redemptionUrl);

        $again = app(BeginGiftCodeRedemption::class)->handle($first->giftCodeId, $player);
        self::assertSame($redemption->redemptionId, $again->redemptionId);
        self::assertSame(2, $again->attempts);
        self::assertSame(1, GiftCodeRedemption::query()->count());

        $confirmed = app(ConfirmGiftCodeRedemption::class)->handle($first->giftCodeId, $player);
        self::assertSame(GiftCodeRedemptionStatus::Redeemed, $confirmed->status);
        self::assertNotNull($confirmed->redeemedAt);

        $afterSuccess = app(BeginGiftCodeRedemption::class)->handle($first->giftCodeId, $player);
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
            $giftCode->giftCodeId,
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
            $giftCode->giftCodeId,
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
}
