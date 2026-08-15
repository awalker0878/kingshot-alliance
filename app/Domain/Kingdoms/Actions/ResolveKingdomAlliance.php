<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use Illuminate\Validation\ValidationException;

final class ResolveKingdomAlliance
{
    public function handle(
        Alliance $alliance,
        string $currentName,
        ?string $currentTag,
        ?string $gameAllianceId,
    ): KingdomAlliance {
        if ($alliance->kingdom_id === null) {
            throw ValidationException::withMessages([
                'tracking' => 'The alliance must have a current Kingdom before game-side alliances can be tracked.',
            ]);
        }

        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages([
                'current_name' => 'Alliance name is required.',
            ]);
        }

        $tag = $this->nullableLine($currentTag);
        $stableId = $this->nullableLine($gameAllianceId);

        if ($stableId !== null) {
            return KingdomAlliance::query()->firstOrCreate(
                [
                    'kingdom_id' => $alliance->kingdom_id,
                    'game_alliance_id' => $stableId,
                ],
                [
                    'current_name' => $name,
                    'current_tag' => $tag,
                    'status' => KingdomAllianceStatus::Active,
                ],
            );
        }

        return KingdomAlliance::query()->create([
            'kingdom_id' => $alliance->kingdom_id,
            'game_alliance_id' => null,
            'current_name' => $name,
            'current_tag' => $tag,
            'status' => KingdomAllianceStatus::Active,
        ]);
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
