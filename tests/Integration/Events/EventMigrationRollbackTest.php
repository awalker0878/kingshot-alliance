<?php

declare(strict_types=1);

namespace Tests\Integration\Events;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class EventMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'event_templates',
        'events',
        'event_occurrences',
        'event_registrations',
        'event_reminder_rules',
        'event_reminder_deliveries',
        'rally_guidance_rules',
        'member_formations',
        'event_recommended_formations',
        'rally_groups',
        'rally_assignments',
    ];

    public function test_phase_three_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $phaseFive = require database_path('migrations/2026_08_07_040000_create_contributions_and_reporting_tables.php');
        $phaseThree = require database_path('migrations/2026_08_07_020000_create_events_and_rallies_tables.php');
        self::assertInstanceOf(Migration::class, $phaseFive);
        self::assertInstanceOf(Migration::class, $phaseThree);

        $phaseFive->down();
        $phaseThree->down();

        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), sprintf('%s should be removed by Phase 3 rollback.', $table));
        }

        $phaseThree->up();
        $phaseFive->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('%s should be restored by Phase 3 migration.', $table));
        }
    }
}
