<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use Illuminate\Validation\ValidationException;

final class ResolveKingdomAlliance
{
    public function handle(Kingdom $kingdom, string $currentName, ?string $currentTag, ?string $gameAllianceId): KingdomAlliance
    {
        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
        }
        $tag = $this->nullableLine($currentTag);
        $stableId = $this->nullableLine($gameAllianceId);
        if ($stableId !== null) {
            return KingdomAlliance::query()->firstOrCreate(['kingdom_id' => $kingdom->id, 'game_alliance_id' => $stableId], ['current_name' => $name, 'current_tag' => $tag, 'status' => KingdomAllianceStatus::Active]);
        }

        return KingdomAlliance::query()->create(['kingdom_id' => $kingdom->id, 'game_alliance_id' => null, 'current_name' => $name, 'current_tag' => $tag, 'status' => KingdomAllianceStatus::Active]);
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
