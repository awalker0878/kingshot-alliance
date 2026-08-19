<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateKingdomAllianceIdentity
{
    public function __construct(private KingdomAllianceReferenceQuery $references) {}

    public function handle(
        string $kingdomAllianceId,
        string $expectedKingdomId,
        string $currentName,
        ?string $currentTag,
        ?string $gameAllianceId = null,
    ): KingdomAllianceReference {
        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
        }

        $tag = $this->nullable($currentTag);
        $stableId = $this->nullable($gameAllianceId);

        DB::transaction(function () use ($kingdomAllianceId, $expectedKingdomId, $name, $tag, $stableId): void {
            $alliance = KingdomAlliance::query()->whereKey($kingdomAllianceId)->lockForUpdate()->firstOrFail();
            if ((string) $alliance->kingdom_id !== $expectedKingdomId) {
                throw ValidationException::withMessages(['kingdom_alliance' => 'The neutral alliance no longer belongs to the expected Kingdom.']);
            }

            $existingStableId = $alliance->game_alliance_id === null ? null : (string) $alliance->game_alliance_id;
            if ($existingStableId !== null && $stableId !== null && $stableId !== $existingStableId) {
                throw ValidationException::withMessages(['game_alliance_id' => 'A stable game alliance ID cannot be changed in place.']);
            }

            $effectiveStableId = $existingStableId ?? $stableId;
            if ($effectiveStableId !== null) {
                $conflict = KingdomAlliance::query()
                    ->where('kingdom_id', $expectedKingdomId)
                    ->where('game_alliance_id', $effectiveStableId)
                    ->whereKeyNot($kingdomAllianceId)
                    ->exists();
                if ($conflict) {
                    throw ValidationException::withMessages(['game_alliance_id' => 'That stable game alliance ID belongs to another neutral alliance reference.']);
                }
            }

            $alliance->forceFill([
                'current_name' => $name,
                'current_tag' => $tag,
                'game_alliance_id' => $effectiveStableId,
            ])->save();
        });

        return $this->references->require($kingdomAllianceId);
    }

    private function nullable(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
