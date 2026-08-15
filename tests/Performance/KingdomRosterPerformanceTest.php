<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Services\RosterIntelligence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KingdomRosterPerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_roster_intelligence_uses_batched_queries_at_realistic_alliance_volume(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-08 20:00:00 UTC'));
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 5200, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'performance-owner',
            'current_name' => 'Performance Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Performance Kingdoms', 'performance-kingdoms');
        self::assertNotNull($alliance->kingdom_id);

        for ($index = 1; $index <= 150; $index++) {
            $player = Player::query()->create([
                'current_kingdom_id' => $alliance->kingdom_id,
                'game_player_id' => 'performance-'.$index,
                'current_name' => 'Performance Player '.$index,
            ]);
            $entry = AllianceRosterEntry::query()->create([
                'alliance_id' => $alliance->id,
                'player_id' => $player->id,
                'observed_name' => $player->current_name,
                'state' => 'active',
                'source' => 'manual',
            ]);

            foreach ([
                35 => 1000 + $index,
                8 => 2000 + $index,
                1 => 3000 + $index,
            ] as $daysAgo => $power) {
                PlayerSnapshot::query()->create([
                    'alliance_id' => $alliance->id,
                    'roster_entry_id' => $entry->id,
                    'player_id' => $player->id,
                    'actor_player_id' => $ownerPlayer->id,
                    'observed_name' => $player->current_name,
                    'power' => $power,
                    'captured_at' => now()->subDays($daysAgo),
                    'source' => 'manual',
                    'idempotency_key' => hash('sha256', $entry->id.'|'.$daysAgo),
                ]);
            }
        }

        $selectQueries = 0;
        $collectQueries = false;
        DB::listen(static function (QueryExecuted $query) use (&$selectQueries, &$collectQueries): void {
            if ($collectQueries && str_starts_with(strtolower(ltrim($query->sql)), 'select')) {
                $selectQueries++;
            }
        });

        $collectQueries = true;
        $metrics = $this->app->make(RosterIntelligence::class)->forAlliance($alliance, now());
        $collectQueries = false;

        self::assertSame(150, $metrics['trackedPlayers']);
        self::assertSame(150, $metrics['recordedPowerPlayers']);
        self::assertSame(150, $metrics['sevenDayTrend']['comparablePlayers']);
        self::assertSame(150, $metrics['thirtyDayTrend']['comparablePlayers']);
        self::assertCount(150, $metrics['comparisons']);
        self::assertLessThanOrEqual(
            8,
            $selectQueries,
            'Roster intelligence must remain batched rather than growing SELECT count with roster size.',
        );
    }
}
