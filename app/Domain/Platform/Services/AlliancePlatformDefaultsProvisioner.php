<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;

final class AlliancePlatformDefaultsProvisioner
{
    public function provision(Alliance $alliance, ?User $actor = null): void
    {
        $now = now();

        DB::table('alliance_plan_assignments')->updateOrInsert(
            ['alliance_id' => $alliance->id],
            [
                'plan_code' => 'standard',
                'assigned_by_user_id' => $actor?->id,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );

        DB::table('alliance_platform_settings')->updateOrInsert(
            ['alliance_id' => $alliance->id],
            [
                'retention_days' => 30,
                'queue_partition' => 'standard',
                'api_access_enabled' => true,
                'webhooks_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
        );
    }
}
