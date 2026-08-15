<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Domain\Platform\Queries\PlatformAdministrationQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PlatformCapacityTest extends TestCase
{
    use RefreshDatabase;

    public function test_platform_dashboard_has_a_hard_fleet_query_bound(): void
    {
        $creator = User::factory()->create();

        for ($i = 0; $i < 205; $i++) {
            Alliance::query()->create([
                'name' => 'Capacity '.str_pad((string) $i, 3, '0', STR_PAD_LEFT),
                'slug' => 'capacity-'.$i,
                'language' => 'en',
                'timezone' => 'UTC',
                'status' => AllianceStatus::Active,
                'created_by_user_id' => $creator->id,
            ]);
        }

        $dashboard = $this->app->make(PlatformAdministrationQuery::class)->dashboard();

        self::assertSame(205, $dashboard['metrics']['alliances']);
        self::assertCount(200, $dashboard['alliances']);
    }

    public function test_horizon_configuration_keeps_integration_workers_separate_from_core(): void
    {
        $production = config('horizon.environments.production');
        self::assertIsArray($production);
        self::assertSame(['default', 'notifications'], $production['core']['queue']);
        self::assertSame(['integrations'], $production['integrations']['queue']);
        self::assertSame(['maintenance'], $production['maintenance']['queue']);
    }
}
