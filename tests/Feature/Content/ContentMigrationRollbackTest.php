<?php

declare(strict_types=1);

namespace Tests\Feature\Content;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class ContentMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_phase_two_content_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $migration = require database_path('migrations/2026_08_07_010000_create_content_domain_tables.php');
        self::assertInstanceOf(Migration::class, $migration);

        $tables = [
            'media_assets',
            'alliance_profiles',
            'alliance_branding_media',
            'content_categories',
            'content_items',
            'content_revisions',
        ];

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table), "{$table} must exist before rollback.");
        }

        $migration->down();

        foreach ($tables as $table) {
            self::assertFalse(Schema::hasTable($table), "{$table} must be removed by rollback.");
        }

        $migration->up();

        foreach ($tables as $table) {
            self::assertTrue(Schema::hasTable($table), "{$table} must be recreated after rollback.");
        }
    }
}
