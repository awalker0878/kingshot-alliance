<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ConfirmGiftCodeRedemption
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $giftCodeId, PlayerReference $player): GiftCodeRedemptionReference
    {
        return DB::transaction(function () use ($giftCodeId, $player): GiftCodeRedemptionReference {
            $giftCode = GiftCode::query()->findOrFail($giftCodeId);
            $redemption = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCodeId)
                ->where('player_id', $player->playerId)
                ->lockForUpdate()
                ->first();

            if (! $redemption instanceof GiftCodeRedemption) {
                throw ValidationException::withMessages([
                    'redemption' => 'Open the official Gift Code Center before confirming redemption.',
                ]);
            }

            if ($redemption->status === GiftCodeRedemptionStatus::Redeemed) {
                return $this->reference($redemption);
            }

            $redemption->forceFill([
                'status' => GiftCodeRedemptionStatus::Redeemed,
                'last_result_code' => 'governor_confirmed',
                'last_message' => 'The Governor confirmed the reward was delivered in-game.',
                'next_attempt_at' => null,
                'redeemed_at' => now(),
            ])->save();

            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'gift_code_redemption_id' => (string) $redemption->id,
                'player_id' => $player->playerId,
                'status' => GiftCodeRedemptionStatus::Redeemed->value,
            ];
            $this->audit->record('game_world.gift_code_redemption_confirmed', $player, $redemption, null, $metadata);
            $this->outbox->record(
                'game_world.gift_code_redemption_confirmed',
                null,
                $redemption,
                $metadata,
                'gift-code-redemption:'.$redemption->id.':confirmed',
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
