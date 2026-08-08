<?php

declare(strict_types=1);

namespace Tests\Integration\Platform;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class PlatformMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'platform_administrators',
        'platform_plans',
        'platform_plan_entitlements',
        'alliance_plan_assignments',
        'alliance_platform_settings',
        'alliance_feature_flags',
        'alliance_usage_snapshots',
        'legal_holds',
        'account_deletion_requests',
        'alliance_data_exports',
        'api_credentials',
        'webhook_subscriptions',
        'webhook_deliveries',
    ];

    public function test_phase_six_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $migration = require database_path('migrations/2026_08_07_050000_create_platform_scale_and_administration_tables.php');
        self::assertInstanceOf(Migration::class, $migration);

        $migration->down();

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), sprintf('%s should be removed by Phase 6 rollback.', $table));
        }
        self::assertFalse(Schema::hasColumn('alliances', 'retention_until'));
        self::assertFalse(Schema::hasColumn('users', 'anonymized_at'));

        $migration->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('%s should be restored by Phase 6 migration.', $table));
        }
        self::assertTrue(Schema::hasColumn('alliances', 'retention_until'));
        self::assertTrue(Schema::hasColumn('users', 'anonymized_at'));
    }
}
