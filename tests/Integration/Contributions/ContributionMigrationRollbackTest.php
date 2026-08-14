<?php

declare(strict_types=1);

namespace Tests\Integration\Contributions;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ContributionMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'contribution_categories',
        'contribution_records',
        'contribution_data_quality_flags',
        'contribution_report_schedules',
        'contribution_report_runs',
    ];

    public function test_phase_five_migrations_roll_back_and_reapply_cleanly(): void
    {
        $permission = require database_path('migrations/2026_08_07_040010_add_contribution_management_permission.php');
        $contributions = require database_path('migrations/2026_08_07_040000_create_contributions_and_reporting_tables.php');
        self::assertInstanceOf(Migration::class, $permission);
        self::assertInstanceOf(Migration::class, $contributions);

        $permission->down();
        self::assertFalse(DB::table('permissions')->where('key', 'contributions.manage')->exists());

        $contributions->down();
        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), sprintf('%s should be removed by Phase 5 rollback.', $table));
        }

        $contributions->up();
        $permission->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('%s should be restored by Phase 5 migration.', $table));
        }
        self::assertFalse(DB::table('permissions')->where('key', 'contributions.manage')->exists());
    }
}
