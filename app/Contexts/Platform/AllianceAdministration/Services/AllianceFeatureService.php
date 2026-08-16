<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Services;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Platform\AllianceAdministration\Models\AllianceFeatureFlag;
use Illuminate\Support\Facades\DB;
use LogicException;

final readonly class AllianceFeatureService
{
    public function set(Alliance $alliance, AccountIdentity $actor, string $key, bool $enabled, ?array $configuration = null): AllianceFeatureFlag
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Alliance feature mutation must run inside a database transaction.');
        }
        AllianceFeatureFlag::query()->upsert([[
            'alliance_id' => $alliance->id,
            'feature_key' => $key,
            'enabled' => $enabled,
            'configuration' => $configuration === null ? null : json_encode($configuration, JSON_THROW_ON_ERROR),
            'updated_by_user_id' => $actor->userId,
            'created_at' => now(),
            'updated_at' => now(),
        ]], ['alliance_id', 'feature_key'], ['enabled', 'configuration', 'updated_by_user_id', 'updated_at']);

        return AllianceFeatureFlag::query()->where('alliance_id', $alliance->id)->where('feature_key', $key)->firstOrFail();
    }

    public function enabled(Alliance $alliance, string $key): bool
    {
        return AllianceFeatureFlag::query()->where('alliance_id', $alliance->id)->where('feature_key', $key)->where('enabled', true)->exists();
    }

    public function all(Alliance $alliance): array
    {
        return array_values(AllianceFeatureFlag::query()->where('alliance_id', $alliance->id)->orderBy('feature_key')->get()->map(static fn (AllianceFeatureFlag $flag): array => [
            'key' => (string) $flag->feature_key,
            'enabled' => $flag->enabled,
            'configuration' => $flag->configuration,
        ])->all());
    }
}
