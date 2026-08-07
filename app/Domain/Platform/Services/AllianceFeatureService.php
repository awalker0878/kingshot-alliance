<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AllianceFeatureFlag;

final class AllianceFeatureService
{
    /** @param array<string, mixed>|null $configuration */
    public function set(Alliance $alliance, User $actor, string $key, bool $enabled, ?array $configuration = null): AllianceFeatureFlag
    {
        return AllianceFeatureFlag::query()->updateOrCreate(
            [
                'alliance_id' => $alliance->id,
                'feature_key' => $key,
            ],
            [
                'enabled' => $enabled,
                'configuration' => $configuration,
                'updated_by_user_id' => $actor->id,
            ],
        );
    }

    public function enabled(Alliance $alliance, string $key): bool
    {
        return AllianceFeatureFlag::query()
            ->where('alliance_id', $alliance->id)
            ->where('feature_key', $key)
            ->where('enabled', true)
            ->exists();
    }

    /** @return list<array{key: string, enabled: bool, configuration: array<string, mixed>|null}> */
    public function all(Alliance $alliance): array
    {
        $flags = AllianceFeatureFlag::query()
            ->where('alliance_id', $alliance->id)
            ->orderBy('feature_key')
            ->get()
            ->map(static fn (AllianceFeatureFlag $flag): array => [
                'key' => (string) $flag->feature_key,
                'enabled' => $flag->enabled,
                'configuration' => $flag->configuration,
            ])
            ->all();

        return array_values($flags);
    }
}
