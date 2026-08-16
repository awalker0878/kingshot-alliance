<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Actions;

use App\Contexts\GameWorld\Enums\KingdomStatus;
use App\Contexts\GameWorld\Models\Kingdom;
use Illuminate\Validation\ValidationException;

final class ResolveKingdom
{
    private const MAX_NUMBER = 2_147_483_647;

    public function handle(int|string|null $number): ?Kingdom
    {
        if ($number === null || trim((string) $number) === '') {
            return null;
        }

        $raw = trim((string) $number);

        if (! ctype_digit($raw)) {
            throw ValidationException::withMessages([
                'kingdom' => 'Kingdom must be a positive numeric kingdom number.',
            ]);
        }

        $digits = ltrim($raw, '0');
        $normalized = (int) ($digits === '' ? '0' : $digits);

        if ($normalized < 1 || $normalized > self::MAX_NUMBER) {
            throw ValidationException::withMessages([
                'kingdom' => 'Kingdom must be between 1 and '.self::MAX_NUMBER.'.',
            ]);
        }

        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $normalized],
            ['status' => KingdomStatus::Active],
        );

        if ($kingdom->status !== KingdomStatus::Active) {
            throw ValidationException::withMessages([
                'kingdom' => 'The selected kingdom is archived.',
            ]);
        }

        return $kingdom;
    }
}
