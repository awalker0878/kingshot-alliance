<?php

declare(strict_types=1);

namespace Tests\Integration\Authorization;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KingdomAuthorizationMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'kingdom_roles',
        'kingdom_role_permissions',
        'kingdom_role_assignments',
    ];

    public function test_kingdom_authorization_migration_rolls_back_and_reapplies_cleanly(): void
    {
        $migration = require database_path('migrations/2026_08_13_000100_create_kingdom_authorization_tables.php');
        self::assertInstanceOf(Migration::class, $migration);

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table));
        }

        $migration->down();
        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table));
        }

        $migration->up();
        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table));
        }
    }
}
