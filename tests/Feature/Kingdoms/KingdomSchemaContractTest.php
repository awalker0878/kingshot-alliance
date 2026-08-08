<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KingdomSchemaContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_class_kingdom_schema_replaces_the_legacy_string_column(): void
    {
        self::assertTrue(Schema::hasTable('kingdoms'));
        self::assertTrue(Schema::hasColumns('kingdoms', [
            'id',
            'number',
            'status',
            'created_at',
            'updated_at',
        ]));
        self::assertTrue(Schema::hasColumn('alliances', 'kingdom_id'));
        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));
    }
}
