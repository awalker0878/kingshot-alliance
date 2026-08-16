<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;

final class AllianceReferenceQuery
{
    public function find(string $allianceId): ?AllianceReference
    {
        $alliance = Alliance::query()->find($allianceId);

        return $alliance instanceof Alliance ? $this->snapshot($alliance) : null;
    }

    public function require(string $allianceId): AllianceReference
    {
        return $this->snapshot(Alliance::query()->findOrFail($allianceId));
    }

    public function exists(string $allianceId): bool
    {
        return Alliance::query()->whereKey($allianceId)->exists();
    }

    private function snapshot(Alliance $alliance): AllianceReference
    {
        return new AllianceReference(
            allianceId: (string) $alliance->id,
            name: (string) $alliance->name,
            slug: (string) $alliance->slug,
            kingdomId: (string) $alliance->kingdom_id,
            language: (string) $alliance->language,
            timezone: (string) $alliance->timezone,
            status: $alliance->status->value,
        );
    }
}
