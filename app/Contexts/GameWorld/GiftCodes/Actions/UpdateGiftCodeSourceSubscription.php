<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;

final class UpdateGiftCodeSourceSubscription
{
    /** @param array<string, mixed> $attributes */
    public function handle(
        string $sourceId,
        string $provider,
        string $transport,
        array $attributes,
        bool $createIfMissing = false,
    ): void {
        $identity = [
            'gift_code_source_id' => $sourceId,
            'provider' => $provider,
            'transport' => $transport,
        ];

        if ($createIfMissing) {
            GiftCodeSourceSubscription::query()->updateOrCreate($identity, $attributes);

            return;
        }

        GiftCodeSourceSubscription::query()
            ->where($identity)
            ->update($attributes);
    }
}
