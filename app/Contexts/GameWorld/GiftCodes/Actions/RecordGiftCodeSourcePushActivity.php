<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use InvalidArgumentException;

final class RecordGiftCodeSourcePushActivity
{
    public function handle(string $sourceId, string $event): void
    {
        $source = GiftCodeSourceRegistry::query()->findOrFail($sourceId);

        match ($event) {
            'signature_failure' => $source->increment('signature_failure_count'),
            'replay_rejection' => $source->increment('replay_rejection_count'),
            'received' => $source->forceFill([
                'last_push_received_at' => now(),
                'last_provider_event_at' => now(),
                'last_health_checked_at' => now(),
            ])->save(),
            default => throw new InvalidArgumentException('Unsupported Gift Code push-activity event.'),
        };
    }
}
