<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeNotificationCampaign;

final class ScheduleGiftCodeNotificationCampaign
{
    public const AVAILABLE = 'gift_code.available';

    public const TRUST_CHANGED = 'gift_code.trust_changed';

    public function handle(string $giftCodeId, GiftCodeStatus $previousStatus, bool $expiryChanged): void
    {
        $giftCode = GiftCode::query()->findOrFail($giftCodeId);
        if ($giftCode->status === GiftCodeStatus::Valid && $previousStatus !== GiftCodeStatus::Valid) {
            $this->record($giftCode, self::AVAILABLE, $previousStatus);
        }

        if ($expiryChanged || in_array($giftCode->status, [
            GiftCodeStatus::Disputed,
            GiftCodeStatus::Quarantined,
            GiftCodeStatus::Invalid,
            GiftCodeStatus::Expired,
        ], true)) {
            $this->record($giftCode, self::TRUST_CHANGED, $previousStatus);
        }
    }

    private function record(GiftCode $giftCode, string $notificationType, GiftCodeStatus $previousStatus): void
    {
        GiftCodeNotificationCampaign::query()->firstOrCreate([
            'gift_code_id' => (string) $giftCode->id,
            'notification_type' => $notificationType,
            'status_revision' => $giftCode->status_revision,
            'expires_revision' => $giftCode->expires_revision,
        ], [
            'metadata' => [
                'previous_status' => $previousStatus->value,
                'status' => $giftCode->status->value,
                'reason_code' => $giftCode->status_reason_code,
                'expires_at' => $giftCode->expires_at?->toIso8601String(),
            ],
        ]);
    }
}
