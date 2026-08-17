<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Actions;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceReference;
use Illuminate\Validation\ValidationException;

final readonly class ResolveKingdomAlliance
{
    public function __construct(private KingdomAllianceReferenceQuery $references) {}

    public function handle(
        string $kingdomId,
        string $currentName,
        ?string $currentTag,
        ?string $gameAllianceId,
    ): KingdomAllianceReference {
        Kingdom::query()->findOrFail($kingdomId);

        $name = trim($currentName);
        if ($name === '') {
            throw ValidationException::withMessages(['current_name' => 'Alliance name is required.']);
        }

        $tag = $this->nullableLine($currentTag);
        $stableId = $this->nullableLine($gameAllianceId);
        $alliance = $stableId === null
            ? KingdomAlliance::query()->create([
                'kingdom_id' => $kingdomId,
                'game_alliance_id' => null,
                'current_name' => $name,
                'current_tag' => $tag,
                'status' => KingdomAllianceStatus::Active,
            ])
            : KingdomAlliance::query()->firstOrCreate(
                ['kingdom_id' => $kingdomId, 'game_alliance_id' => $stableId],
                ['current_name' => $name, 'current_tag' => $tag, 'status' => KingdomAllianceStatus::Active],
            );

        return $this->references->require((string) $alliance->id);
    }

    private function nullableLine(?string $value): ?string
    {
        $value = $value === null ? null : trim($value);

        return $value === '' ? null : $value;
    }
}
