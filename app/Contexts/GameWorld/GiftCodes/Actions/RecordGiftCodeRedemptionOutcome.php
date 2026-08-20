<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final readonly class RecordGiftCodeRedemptionOutcome
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $giftCodeId,
        PlayerReference $player,
        string $provider,
        GiftCodeRedemptionOutcome $outcome,
    ): GiftCodeRedemptionReference {
        return DB::transaction(function () use ($giftCodeId, $player, $provider, $outcome): GiftCodeRedemptionReference {
            $giftCode = GiftCode::query()->findOrFail($giftCodeId);
            $redemption = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCode->id)
                ->where('player_id', $player->playerId)
                ->lockForUpdate()
                ->first();

            if (! $redemption instanceof GiftCodeRedemption) {
                $redemption = new GiftCodeRedemption([
                    'gift_code_id' => (string) $giftCode->id,
                    'player_id' => $player->playerId,
                    'kingdom_id' => $player->kingdomId,
                    'attempts' => 0,
                ]);
            } elseif ($redemption->status->successful()
                || ($redemption->status->retryable() && $redemption->next_attempt_at?->isFuture())) {
                return $this->reference($redemption);
            }

            $attempts = $redemption->attempts + 1;
            $retryAt = $outcome->retryAt;
            if ($retryAt === null && $outcome->status->retryable()) {
                $retryAt = CarbonImmutable::now()->addMinutes((int) min(60, 2 ** min($attempts, 6)));
            }

            $redemption->fill([
                'kingdom_id' => $player->kingdomId,
                'status' => $outcome->status,
                'provider' => $provider,
                'attempts' => $attempts,
                'last_result_code' => $outcome->resultCode,
                'last_message' => $outcome->message,
                'redemption_url' => $outcome->redemptionUrl,
                'last_attempt_at' => now(),
                'next_attempt_at' => $retryAt,
                'redeemed_at' => $outcome->status->successful() ? now() : $redemption->redeemed_at,
            ]);
            $redemption->save();

            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'gift_code_redemption_id' => (string) $redemption->id,
                'player_id' => $player->playerId,
                'kingdom_id' => $player->kingdomId,
                'status' => $outcome->status->value,
                'provider' => $provider,
                'attempts' => $attempts,
                'result_code' => $outcome->resultCode,
            ];
            $this->audit->record('game_world.gift_code_redemption_recorded', $player, $redemption, null, $metadata);
            $this->outbox->record(
                'game_world.gift_code_redemption_recorded',
                null,
                $redemption,
                $metadata,
                'gift-code-redemption:'.$redemption->id.':'.$attempts,
                'player:'.$player->playerId,
            );

            return $this->reference($redemption);
        });
    }

    private function reference(GiftCodeRedemption $redemption): GiftCodeRedemptionReference
    {
        return new GiftCodeRedemptionReference(
            (string) $redemption->id,
            $redemption->status,
            $redemption->attempts,
            $redemption->redemption_url,
            $redemption->next_attempt_at,
            $redemption->redeemed_at,
        );
    }
}
