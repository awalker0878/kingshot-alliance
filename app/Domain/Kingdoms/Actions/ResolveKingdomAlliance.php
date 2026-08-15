<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomAllianceStatus;
use App\Domain\Kingdoms\Models\KingdomAlliance;
use Illuminate\Validation\ValidationException;

final class ResolveKingdomAlliance
{
    public function handle(
        Alliance $alliance,
        string $currentName,
        ?string $currentTag,
        string $gameAllianceId,
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

        $stableId = trim($gameAllianceId);
        if ($stableId === '') {
            throw ValidationException::withMessages([
                'game_alliance_id' => 'Game alliance ID is required.',
            ]);
        }

        $tag = $this->nullableLine($currentTag);
        $reference = KingdomAlliance::query()
            ->where('game_alliance_id', $stableId)
            ->lockForUpdate()
            ->first();

        if ($reference instanceof KingdomAlliance) {
            if ((string) $reference->kingdom_id !== (string) $alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'game_alliance_id' => 'That game alliance ID already belongs to a different Kingdom.',
                ]);
            }

            if ($reference->current_name !== $name || $reference->current_tag !== $tag) {
                $reference->forceFill([
                    'current_name' => $name,
                    'current_tag' => $tag,
                ])->save();
            }

            return $reference;
        }

        return KingdomAlliance::query()->create([
            'kingdom_id' => $alliance->kingdom_id,
            'game_alliance_id' => $stableId,
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
