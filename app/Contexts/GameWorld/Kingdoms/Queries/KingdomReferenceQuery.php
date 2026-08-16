<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference;

final class KingdomReferenceQuery
{
    public function find(string $kingdomId): ?KingdomReference
    {
        $kingdom = Kingdom::query()->find($kingdomId);

        return $kingdom instanceof Kingdom ? $this->snapshot($kingdom) : null;
    }

    public function require(string $kingdomId): KingdomReference
    {
        return $this->snapshot(Kingdom::query()->findOrFail($kingdomId));
    }

    public function findByNumber(int $number): ?KingdomReference
    {
        $kingdom = Kingdom::query()->where('number', $number)->first();

        return $kingdom instanceof Kingdom ? $this->snapshot($kingdom) : null;
    }

    private function snapshot(Kingdom $kingdom): KingdomReference
    {
        return new KingdomReference(
            kingdomId: (string) $kingdom->id,
            number: (int) $kingdom->number,
            status: is_object($kingdom->status) && property_exists($kingdom->status, 'value')
                ? (string) $kingdom->status->value
                : (string) $kingdom->status,
        );
    }
}
