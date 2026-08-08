<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Enums\KingdomStatus;
use App\Domain\Kingdoms\Models\Kingdom;
use Illuminate\Validation\ValidationException;

final class ResolveKingdom
{
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

        $normalized = (int) ltrim($raw, '0');

        if ($normalized < 1 || $normalized > 4_294_967_295) {
            throw ValidationException::withMessages([
                'kingdom' => 'Kingdom must be between 1 and 4294967295.',
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
