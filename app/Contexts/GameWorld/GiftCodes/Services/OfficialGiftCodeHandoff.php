<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeRedemptionProvider;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final readonly class OfficialGiftCodeHandoff implements GiftCodeRedemptionProvider
{
    public function __construct(private string $redemptionUrl) {}

    public function name(): string
    {
        return 'century_games_handoff';
    }

    public function begin(GiftCode $giftCode, PlayerReference $player): GiftCodeRedemptionOutcome
    {
        if ($player->gamePlayerId === null || trim($player->gamePlayerId) === '') {
            return new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::PermanentFailure,
                'missing_player_id',
                'Add this Governor’s in-game Player ID before redeeming Gift Codes.',
            );
        }

        return new GiftCodeRedemptionOutcome(
            GiftCodeRedemptionStatus::AwaitingConfirmation,
            'official_handoff_ready',
            'Continue at the official Kingshot Gift Code Center, then confirm the result here.',
            $this->redemptionUrl,
        );
    }
}
