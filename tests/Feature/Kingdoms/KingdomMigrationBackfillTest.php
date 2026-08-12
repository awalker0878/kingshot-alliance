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
        $importMigration = require database_path('migrations/2026_08_08_143000_create_kingdom_roster_imports.php');
        $transferPlanMigration = require database_path('migrations/2026_08_09_090000_create_transfer_plans.php');
        $transferParticipantMigration = require database_path('migrations/2026_08_09_100000_create_transfer_participants.php');
        $transferGroupMigration = require database_path('migrations/2026_08_09_110000_create_transfer_groups.php');
        $transferReadinessMigration = require database_path('migrations/2026_08_09_120000_create_transfer_readiness_and_blockers.php');
        $transferCompletionMigration = require database_path('migrations/2026_08_09_130000_create_transfer_completions.php');
        $kingdomAllianceMigration = require database_path('migrations/2026_08_09_140000_create_kingdom_alliance_tracking.php');
        $kingdomAllianceObservationMigration = require database_path('migrations/2026_08_09_150000_create_kingdom_alliance_observations.php');
        $kingdomAllianceDiplomacyMigration = require database_path('migrations/2026_08_10_090000_create_kingdom_alliance_diplomacy.php');
        $kingdomAllianceContactMigration = require database_path('migrations/2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php');
        $ingestionMigration = require database_path('migrations/2026_08_11_190000_create_kingdom_ingestion_foundation.php');
        $ingestionSchedulingMigration = require database_path('migrations/2026_08_11_220000_add_ingestion_scheduling.php');
        self::assertInstanceOf(Migration::class, $kingdomMigration);
        self::assertInstanceOf(Migration::class, $rosterMigration);
        self::assertInstanceOf(Migration::class, $snapshotMigration);
        self::assertInstanceOf(Migration::class, $importMigration);
        self::assertInstanceOf(Migration::class, $transferPlanMigration);
        self::assertInstanceOf(Migration::class, $transferParticipantMigration);
        self::assertInstanceOf(Migration::class, $transferGroupMigration);
        self::assertInstanceOf(Migration::class, $transferReadinessMigration);
        self::assertInstanceOf(Migration::class, $transferCompletionMigration);
        self::assertInstanceOf(Migration::class, $kingdomAllianceMigration);
        self::assertInstanceOf(Migration::class, $kingdomAllianceObservationMigration);
        self::assertInstanceOf(Migration::class, $kingdomAllianceDiplomacyMigration);
        self::assertInstanceOf(Migration::class, $kingdomAllianceContactMigration);
        self::assertInstanceOf(Migration::class, $ingestionMigration);
        self::assertInstanceOf(Migration::class, $ingestionSchedulingMigration);

        // Exercise the full Kingdoms dependency order from newest tenant workflow to
        // the first-class Kingdom reference it ultimately depends on.
        $ingestionSchedulingMigration->down();
        $ingestionMigration->down();
        $kingdomAllianceContactMigration->down();
        $kingdomAllianceDiplomacyMigration->down();
        $kingdomAllianceObservationMigration->down();
        $kingdomAllianceMigration->down();
        $transferCompletionMigration->down();
        $transferReadinessMigration->down();
        $transferGroupMigration->down();
        $transferParticipantMigration->down();
        $transferPlanMigration->down();
        $importMigration->down();
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
        $importMigration->up();
        $transferPlanMigration->up();
        $transferParticipantMigration->up();
        $transferGroupMigration->up();
        $transferReadinessMigration->up();
        $transferCompletionMigration->up();
        $kingdomAllianceMigration->up();
        $kingdomAllianceObservationMigration->up();
        $kingdomAllianceDiplomacyMigration->up();
        $kingdomAllianceContactMigration->up();
        $ingestionMigration->up();
        $ingestionSchedulingMigration->up();

        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));
        self::assertTrue(Schema::hasColumn('alliances', 'kingdom_id'));
        self::assertTrue(Schema::hasTable('kingdom_players'));
        self::assertTrue(Schema::hasTable('alliance_roster_entries'));
        self::assertTrue(Schema::hasTable('player_snapshots'));
        self::assertTrue(Schema::hasTable('kingdom_roster_imports'));
        self::assertTrue(Schema::hasTable('transfer_plans'));
        self::assertTrue(Schema::hasTable('transfer_participants'));
        self::assertTrue(Schema::hasTable('transfer_groups'));
        self::assertTrue(Schema::hasTable('transfer_readiness_transitions'));
        self::assertTrue(Schema::hasTable('transfer_blockers'));
        self::assertTrue(Schema::hasTable('transfer_completions'));
        self::assertTrue(Schema::hasTable('kingdom_alliances'));
        self::assertTrue(Schema::hasTable('tracked_kingdom_alliances'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_observations'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_relationships'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_transitions'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_contacts'));
        self::assertTrue(Schema::hasTable('kingdom_ingestion_subscriptions'));
        self::assertTrue(Schema::hasTable('kingdom_ingestion_batches'));
        self::assertTrue(Schema::hasTable('kingdom_ingestion_candidates'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_subscriptions', 'next_run_at'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_subscriptions', 'circuit_open_until'));
        self::assertTrue(Schema::hasColumn('kingdom_ingestion_batches', 'next_source_cursor'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_observations', 'idempotency_key'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_observations', 'corrects_observation_id'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_observations', 'invalidated_at'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_relationships', 'current_state'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_relationships', 'review_at'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_relationships', 'expires_at'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_transitions', 'from_state'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_transitions', 'to_state'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_contacts', 'channel_type'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_contacts', 'handle'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_contacts', 'state'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_contacts', 'last_verified_at'));
        self::assertTrue(Schema::hasColumn('kingdom_alliance_diplomacy_contacts', 'manager_notes'));
        self::assertTrue(Schema::hasColumn('transfer_participants', 'transfer_group_id'));
        self::assertTrue(Schema::hasColumn('transfer_participants', 'readiness_state'));
        self::assertTrue(Schema::hasColumn('player_snapshots', 'roster_import_id'));
        self::assertSame(1, DB::table('kingdoms')->where('number', 2400)->count());

        $kingdomId = DB::table('kingdoms')->where('number', 2400)->value('id');
        self::assertIsString($kingdomId);
        self::assertSame($kingdomId, DB::table('alliances')->where('id', $first->id)->value('kingdom_id'));
        self::assertSame($kingdomId, DB::table('alliances')->where('id', $second->id)->value('kingdom_id'));
    }

    public function test_k3_migrations_round_trip_cleanly_to_the_accepted_k2_baseline(): void
    {
        $trackingMigration = require database_path('migrations/2026_08_09_140000_create_kingdom_alliance_tracking.php');
        $observationMigration = require database_path('migrations/2026_08_09_150000_create_kingdom_alliance_observations.php');
        $diplomacyMigration = require database_path('migrations/2026_08_10_090000_create_kingdom_alliance_diplomacy.php');
        $contactMigration = require database_path('migrations/2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php');

        foreach ([$trackingMigration, $observationMigration, $diplomacyMigration, $contactMigration] as $migration) {
            self::assertInstanceOf(Migration::class, $migration);
        }

        self::assertTrue(Schema::hasTable('transfer_completions'));
        self::assertTrue(Schema::hasTable('alliance_roster_entries'));
        self::assertTrue(Schema::hasTable('player_snapshots'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_contacts'));

        $contactMigration->down();
        $diplomacyMigration->down();
        $observationMigration->down();
        $trackingMigration->down();

        self::assertTrue(Schema::hasTable('transfer_completions'));
        self::assertTrue(Schema::hasTable('alliance_roster_entries'));
        self::assertTrue(Schema::hasTable('player_snapshots'));
        self::assertFalse(Schema::hasTable('kingdom_alliances'));
        self::assertFalse(Schema::hasTable('tracked_kingdom_alliances'));
        self::assertFalse(Schema::hasTable('kingdom_alliance_observations'));
        self::assertFalse(Schema::hasTable('kingdom_alliance_diplomacy_relationships'));
        self::assertFalse(Schema::hasTable('kingdom_alliance_diplomacy_transitions'));
        self::assertFalse(Schema::hasTable('kingdom_alliance_diplomacy_contacts'));

        $trackingMigration->up();
        $observationMigration->up();
        $diplomacyMigration->up();
        $contactMigration->up();

        self::assertTrue(Schema::hasTable('transfer_completions'));
        self::assertTrue(Schema::hasTable('alliance_roster_entries'));
        self::assertTrue(Schema::hasTable('player_snapshots'));
        self::assertTrue(Schema::hasTable('kingdom_alliances'));
        self::assertTrue(Schema::hasTable('tracked_kingdom_alliances'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_observations'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_relationships'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_transitions'));
        self::assertTrue(Schema::hasTable('kingdom_alliance_diplomacy_contacts'));
    }
}
