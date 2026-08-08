<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class KingdomMigrationBackfillTest extends TestCase
{
    use RefreshDatabase;

    public function test_legacy_variants_backfill_to_one_canonical_kingdom_and_remove_the_old_column(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'Legacy First', 'legacy-first', 2400);
        $second = $createAlliance->handle($owner, 'Legacy Second', 'legacy-second', 2400);
        $kingdomMigration = require database_path('migrations/2026_08_08_140500_create_kingdoms_and_link_alliances.php');
        $rosterMigration = require database_path('migrations/2026_08_08_141000_create_kingdom_roster_tables.php');
        $snapshotMigration = require database_path('migrations/2026_08_08_142000_create_player_snapshots.php');
        self::assertInstanceOf(Migration::class, $kingdomMigration);
        self::assertInstanceOf(Migration::class, $rosterMigration);
        self::assertInstanceOf(Migration::class, $snapshotMigration);

        // Exercise Slice A in the dependency order a real rollback uses after Slice C1 exists.
        $snapshotMigration->down();
        $rosterMigration->down();
        $kingdomMigration->down();

        self::assertTrue(Schema::hasColumn('alliances', 'kingdom'));
        self::assertFalse(Schema::hasColumn('alliances', 'kingdom_id'));

        DB::table('alliances')->where('id', $first->id)->update(['kingdom' => 'K #002400']);
        DB::table('alliances')->where('id', $second->id)->update(['kingdom' => 'Kingdom 2400']);

        $kingdomMigration->up();
        $rosterMigration->up();
        $snapshotMigration->up();

        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));
        self::assertTrue(Schema::hasColumn('alliances', 'kingdom_id'));
        self::assertTrue(Schema::hasTable('kingdom_players'));
        self::assertTrue(Schema::hasTable('alliance_roster_entries'));
        self::assertTrue(Schema::hasTable('player_snapshots'));
        self::assertSame(1, DB::table('kingdoms')->where('number', 2400)->count());

        $kingdomId = DB::table('kingdoms')->where('number', 2400)->value('id');
        self::assertIsString($kingdomId);
        self::assertSame($kingdomId, DB::table('alliances')->where('id', $first->id)->value('kingdom_id'));
        self::assertSame($kingdomId, DB::table('alliances')->where('id', $second->id)->value('kingdom_id'));
    }
}
