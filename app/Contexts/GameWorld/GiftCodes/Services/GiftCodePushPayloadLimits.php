<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use Illuminate\Validation\ValidationException;

final class GiftCodePushPayloadLimits
{
    public function assertBounded(string $payload): void
    {
        $maximum = max(16_384, min(2_000_000, (int) config('game_world.gift_codes.push_payload_max_bytes', 262_144)));
        if (strlen($payload) > $maximum) {
            throw ValidationException::withMessages([
                'payload' => 'The Gift Code provider delivery exceeded the configured size bound.',
            ]);
        }
    }
}
