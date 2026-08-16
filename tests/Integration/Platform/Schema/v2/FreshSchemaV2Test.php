<?php

declare(strict_types=1);

namespace Tests\Integration\Platform\Schema\v2;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class FreshSchemaV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_v2_schema_contains_authoritative_core_tables(): void
    {
        foreach ([
            'users',
            'kingdoms',
            'players',
            'alliances',
            'alliance_memberships',
            'events',
            'outbox_messages',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), "Missing V2 table: {$table}");
        }
    }
}
