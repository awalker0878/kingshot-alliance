<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;
use Illuminate\Validation\ValidationException;

final readonly class ResolveKingdom
{
    private const MAX_NUMBER = 2_147_483_647;

    public function __construct(private KingdomReferenceQuery $kingdoms) {}

    public function handle(int|string|null $number): ?KingdomReference
    {
        if ($number === null || trim((string) $number) === '') {
            return null;
        }

        $raw = trim((string) $number);
        if (! ctype_digit($raw)) {
            throw ValidationException::withMessages(['kingdom' => 'Kingdom must be a positive numeric kingdom number.']);
        }

        $digits = ltrim($raw, '0');
        $normalized = (int) ($digits === '' ? '0' : $digits);
        if ($normalized < 1 || $normalized > self::MAX_NUMBER) {
            throw ValidationException::withMessages(['kingdom' => 'Kingdom must be between 1 and '.self::MAX_NUMBER.'.']);
        }

        $kingdom = Kingdom::query()->firstOrCreate(
            ['number' => $normalized],
            ['status' => KingdomStatus::Active],
        );
        if ($kingdom->status !== KingdomStatus::Active) {
            throw ValidationException::withMessages(['kingdom' => 'The selected kingdom is archived.']);
        }

        return $this->kingdoms->require((string) $kingdom->id);
    }
}
