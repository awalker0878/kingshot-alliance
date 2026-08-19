<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeRedemptionProvider;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final readonly class BeginGiftCodeRedemption
{
    public function __construct(
        private GiftCodeRedemptionProvider $provider,
        private RecordGiftCodeRedemptionOutcome $record,
    ) {}

    public function handle(string $giftCodeId, PlayerReference $player): GiftCodeRedemption
    {
        $giftCode = GiftCode::query()->findOrFail($giftCodeId);

        $outcome = match (true) {
            $giftCode->status !== GiftCodeStatus::Active => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'code_unavailable',
                'This Gift Code is no longer active.',
            ),
            $giftCode->expires_at !== null && $giftCode->expires_at->isPast() => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'code_expired',
                'This Gift Code has expired.',
            ),
            default => $this->provider->begin($giftCode, $player),
        };

        return $this->record->handle($giftCode, $player, $this->provider->name(), $outcome);
    }
}
