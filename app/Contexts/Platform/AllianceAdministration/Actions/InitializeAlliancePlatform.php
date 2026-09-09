<?php

declare(strict_types=1);

namespace App\Contexts\Platform\AllianceAdministration\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlanAssignment;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\AllianceAdministration\Queries\PlanEntitlementQuery;
use Illuminate\Support\Facades\DB;

final readonly class InitializeAlliancePlatform
{
    public function __construct(private AllianceReferenceQuery $alliances) {}

    public function handle(string $allianceId): void
    {
        DB::transaction(function () use ($allianceId): void {
            $this->alliances->lockCurrent($allianceId);
            AlliancePlanAssignment::query()->firstOrCreate(['alliance_id' => $allianceId], [
                'plan_code' => PlanEntitlementQuery::DEFAULT_PLAN_CODE,
                'assigned_at' => now(),
            ]);
            AlliancePlatformSetting::query()->firstOrCreate(['alliance_id' => $allianceId], [
                'retention_days' => 30,
                'queue_partition' => 'standard',
                'api_access_enabled' => true,
                'webhooks_enabled' => true,
            ]);
        });
    }
}
