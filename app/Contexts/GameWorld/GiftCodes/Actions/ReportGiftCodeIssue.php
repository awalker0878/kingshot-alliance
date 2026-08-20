<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use InvalidArgumentException;

final readonly class ReportGiftCodeIssue
{
    public function __construct(private RecordGiftCodeRedemptionOutcome $record) {}

    public function handle(
        string $giftCodeId,
        PlayerReference $player,
        string $issue,
    ): GiftCodeRedemptionReference {
        $outcome = match ($issue) {
            'invalid' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::InvalidCode,
                'governor_reported_invalid',
                'A Governor reported that the official center rejected this Gift Code.',
            ),
            'expired' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'governor_reported_expired',
                'A Governor reported that the official center marked this Gift Code as expired.',
            ),
            default => throw new InvalidArgumentException('Gift Code issue is unsupported.'),
        };

        return $this->record->handle($giftCodeId, $player, 'governor_report', $outcome);
    }
}
