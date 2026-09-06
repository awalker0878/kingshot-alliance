<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;
use InvalidArgumentException;

final class CompleteGiftCodePushDelivery
{
    public function handle(string $deliveryId, string $status, ?string $errorCode = null): void
    {
        if (! in_array($status, ['processed', 'quarantined', 'failed', 'ignored'], true)) {
            throw new InvalidArgumentException('Unsupported Gift Code push-delivery completion status.');
        }

        $delivery = GiftCodeSourceDelivery::query()->findOrFail($deliveryId);
        $delivery->forceFill([
            'processing_status' => $status,
            'error_code' => $errorCode,
            'processed_at' => now(),
        ])->save();
    }
}
