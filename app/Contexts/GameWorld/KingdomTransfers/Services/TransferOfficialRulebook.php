<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;

final class TransferOfficialRulebook
{
    public const int TRANSFER_COOLDOWN_DAYS = 25;

    public const int MAX_CHARACTERS_PER_KINGDOM = 4;

    public const int MIN_CHARACTER_AGE_THRESHOLD_DAYS = 90;

    public const int MAX_CHARACTER_AGE_THRESHOLD_DAYS = 180;

    public const int MIN_REQUIRED_TRANSFER_PASSES = 1;

    public const int MAX_REQUIRED_TRANSFER_PASSES = 50;

    public const int MAX_SPECIAL_INVITES = 3;

    /** @return array{total:int,ordinary_invites:int,transfer_opens:int}|null */
    public function capacity(TransferKingdomClassification $classification): ?array
    {
        return match ($classification) {
            TransferKingdomClassification::Ordinary => [
                'total' => 55,
                'ordinary_invites' => 35,
                'transfer_opens' => 20,
            ],
            TransferKingdomClassification::Leading => [
                'total' => 30,
                'ordinary_invites' => 20,
                'transfer_opens' => 10,
            ],
            TransferKingdomClassification::Unknown => null,
        };
    }

    public function validCharacterAgeThreshold(int $days): bool
    {
        return $days >= self::MIN_CHARACTER_AGE_THRESHOLD_DAYS
            && $days <= self::MAX_CHARACTER_AGE_THRESHOLD_DAYS;
    }
}
