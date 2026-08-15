<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Models\AllianceFeatureFlag;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllianceFeatureService
{
    /** @param array<string, mixed>|null $configuration */
    public function set(
        Alliance $alliance,
        User $actor,
        string $key,
        bool $enabled,
        ?array $configuration = null,
    ): AllianceFeatureFlag {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance feature mutation must run inside a database transaction.');
        }

        // The database uniqueness constraint on (alliance_id, feature_key) is the hard
        // race guard; upsert avoids check-then-insert behavior for a previously unset flag.
        AllianceFeatureFlag::query()->upsert([[
            'alliance_id' => $alliance->id,
            'feature_key' => $key,
            'enabled' => $enabled,
            'configuration' => $configuration === null ? null : json_encode($configuration, JSON_THROW_ON_ERROR),
            'updated_by_user_id' => $actor->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['alliance_id', 'feature_key'], [
            'enabled',
            'configuration',
            'updated_by_user_id',
            'updated_at',
        ]);

        return AllianceFeatureFlag::query()
            ->where('alliance_id', $alliance->id)
            ->where('feature_key', $key)
            ->firstOrFail();
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
