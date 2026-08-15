<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingdomFreshSchemaTest extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_contains_only_the_final_player_authority_contract(): void
    {
        foreach ([
            'kingdoms',
            'players',
            'alliances',
            'alliance_memberships',
            'alliance_roster_entries',
            'player_snapshots',
            'transfer_plans',
            'transfer_participants',
            'transfer_groups',
            'transfer_readiness_transitions',
            'transfer_blockers',
            'transfer_completions',
            'kingdom_alliances',
            'tracked_kingdom_alliances',
            'kingdom_alliance_observations',
            'kingdom_alliance_diplomacy_relationships',
            'kingdom_alliance_diplomacy_transitions',
            'kingdom_alliance_diplomacy_contacts',
            'kingdom_ingestion_subscriptions',
            'kingdom_ingestion_batches',
            'kingdom_ingestion_candidates',
            'kingdom_intelligence_shares',
            'kingdom_intelligence_share_targets',
        ] as $table) {
            self::assertTrue(Schema::hasTable($table), 'Missing fresh-schema table: '.$table);
        }

        $this->assertColumns('players', ['user_id', 'current_kingdom_id', 'game_player_id', 'current_name']);
        $this->assertColumns('alliances', ['kingdom_id', 'name', 'slug', 'status']);
        self::assertFalse(Schema::hasColumn('alliances', 'kingdom'));

        $this->assertColumns('alliance_memberships', ['alliance_id', 'player_id', 'status', 'rank']);
        self::assertFalse(Schema::hasColumn('alliance_memberships', 'user_id'));

        $this->assertColumns('alliance_roster_entries', ['alliance_id', 'player_id', 'state']);
        self::assertFalse(Schema::hasColumn('alliance_roster_entries', 'membership_id'));

        $this->assertColumns('player_snapshots', ['alliance_id', 'player_id', 'actor_player_id']);
        self::assertFalse(Schema::hasColumn('player_snapshots', 'actor_user_id'));

        $this->assertColumns('transfer_participants', ['transfer_plan_id', 'player_id', 'roster_entry_id']);
        $this->assertColumns('transfer_groups', ['transfer_plan_id', 'coordinator_player_id']);
        self::assertFalse(Schema::hasColumn('transfer_groups', 'coordinator_membership_id'));
        $this->assertColumns('transfer_readiness_transitions', ['transfer_participant_id', 'actor_player_id']);
        $this->assertColumns('transfer_blockers', ['transfer_participant_id', 'created_by_player_id', 'resolved_by_player_id']);
        $this->assertColumns('transfer_completions', ['transfer_participant_id', 'completed_by_player_id']);

        $this->assertColumns('kingdom_alliance_observations', ['actor_player_id', 'invalidated_by_player_id']);
        self::assertFalse(Schema::hasColumn('kingdom_alliance_observations', 'actor_user_id'));
        self::assertFalse(Schema::hasColumn('kingdom_alliance_observations', 'invalidated_by_user_id'));

        $this->assertColumns('kingdom_alliance_diplomacy_relationships', ['last_transition_player_id']);
        $this->assertColumns('kingdom_alliance_diplomacy_transitions', ['actor_player_id']);
        $this->assertColumns('kingdom_alliance_diplomacy_contacts', [
            'created_by_player_id',
            'updated_by_player_id',
            'deactivated_by_player_id',
        ]);

        $this->assertColumns('kingdom_intelligence_shares', [
            'invited_by_player_id',
            'accepted_by_player_id',
            'declined_by_player_id',
            'revoked_by_player_id',
        ]);
        $this->assertColumns('kingdom_intelligence_share_targets', [
            'shared_by_player_id',
            'removed_by_player_id',
        ]);
    }

    public function test_shared_intelligence_invitation_hash_nullability_round_trips_player_attributed_terminal_rows(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 7610, 'status' => 'active']);
        [$sourceOwner, $sourcePlayer, $source] = $this->ownerAlliance($kingdom, 'Hash Source', 'hash-source');
        [$recipientOwner, $recipientPlayer, $recipient] = $this->ownerAlliance($kingdom, 'Hash Recipient', 'hash-recipient');
        self::assertNotSame($sourceOwner->id, $recipientOwner->id);

        $shareId = (string) Str::ulid();
        $now = now()->startOfSecond();

        DB::table('kingdom_intelligence_shares')->insert([
            'id' => $shareId,
            'source_alliance_id' => $source->id,
            'recipient_alliance_id' => $recipient->id,
            'kingdom_id' => $source->kingdom_id,
            'invitation_token_hash' => null,
            'state' => 'active',
            'invited_by_player_id' => $sourcePlayer->id,
            'accepted_by_player_id' => $recipientPlayer->id,
            'declined_by_player_id' => null,
            'revoked_by_player_id' => null,
            'invitation_expires_at' => $now->copy()->addDay(),
            'invitation_used_at' => $now,
            'accepted_at' => $now,
            'declined_at' => null,
            'revoked_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $migration = require database_path('migrations/2026_08_12_030000_make_kingdom_intelligence_share_invitation_hash_nullable.php');
        self::assertInstanceOf(Migration::class, $migration);

        $migration->down();

        $retiredHash = DB::table('kingdom_intelligence_shares')
            ->where('id', $shareId)
            ->value('invitation_token_hash');
        self::assertIsString($retiredHash);
        self::assertMatchesRegularExpression('/\A[a-f0-9]{64}\z/', $retiredHash);
        self::assertSame('active', DB::table('kingdom_intelligence_shares')->where('id', $shareId)->value('state'));

        $migration->up();

        self::assertNull(DB::table('kingdom_intelligence_shares')->where('id', $shareId)->value('invitation_token_hash'));
        self::assertSame($recipient->id, DB::table('kingdom_intelligence_shares')->where('id', $shareId)->value('recipient_alliance_id'));
        self::assertSame($sourcePlayer->id, DB::table('kingdom_intelligence_shares')->where('id', $shareId)->value('invited_by_player_id'));
        self::assertSame($recipientPlayer->id, DB::table('kingdom_intelligence_shares')->where('id', $shareId)->value('accepted_by_player_id'));
    }

    /** @param list<string> $columns */
    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            self::assertTrue(Schema::hasColumn($table, $column), sprintf('Missing %s.%s', $table, $column));
        }
    }

    /** @return array{0: User, 1: Player, 2: Alliance} */
    private function ownerAlliance(Kingdom $kingdom, string $name, string $slug): array
    {
        $owner = User::factory()->create();
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'owner-'.$slug,
            'current_name' => $name.' Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, $name, $slug);

        return [$owner, $player, $alliance];
    }
}
