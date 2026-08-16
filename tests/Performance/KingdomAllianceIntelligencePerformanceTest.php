<?php

declare(strict_types=1);

namespace Tests\Performance;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\KingdomAlliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacy;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\ReadModels\KingdomIntelligence\KingdomAllianceIntelligence;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class KingdomAllianceIntelligencePerformanceTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_intelligence_dashboard_uses_batched_queries_at_realistic_kingdom_volume(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-10 16:00:00 UTC'));
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 6601, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'alliance-intelligence-performance-owner',
            'current_name' => 'Alliance Intelligence Performance Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Alliance Intelligence Performance', 'alliance-intelligence-performance');
        self::assertNotNull($alliance->kingdom_id);

        for ($index = 1; $index <= 120; $index++) {
            $reference = KingdomAlliance::query()->create([
                'kingdom_id' => $alliance->kingdom_id,
                'game_alliance_id' => 'performance-alliance-'.$index,
                'current_name' => sprintf('Alliance %03d', $index),
                'current_tag' => sprintf('A%03d', $index),
                'status' => 'active',
            ]);
            $tracking = TrackedKingdomAlliance::query()->create([
                'alliance_id' => $alliance->id,
                'kingdom_alliance_id' => $reference->id,
                'kingdom_id' => $alliance->kingdom_id,
                'state' => 'active',
            ]);

            foreach ([45, 35, 8, 3, 1] as $daysAgo) {
                KingdomAllianceObservation::query()->create([
                    'alliance_id' => $alliance->id,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'kingdom_alliance_id' => $reference->id,
                    'actor_player_id' => $ownerPlayer->id,
                    'observed_name' => $reference->current_name,
                    'observed_tag' => $reference->current_tag,
                    'power' => ($index * 1000) + (50 - $daysAgo),
                    'member_count' => 50 + ($index % 20),
                    'captured_at' => now()->subDays($daysAgo),
                    'source' => 'manual',
                    'idempotency_key' => hash('sha256', $tracking->id.'|'.$daysAgo),
                ]);
            }

            KingdomAllianceDiplomacy::query()->create([
                'alliance_id' => $alliance->id,
                'tracked_kingdom_alliance_id' => $tracking->id,
                'kingdom_alliance_id' => $reference->id,
                'current_state' => $index % 3 === 0 ? 'nap' : 'neutral',
                'effective_at' => now()->subDays(20),
                'review_at' => $index % 10 === 0 ? now()->subDay() : now()->addDays(10),
                'last_transition_player_id' => $ownerPlayer->id,
            ]);

            if ($index % 2 === 0) {
                KingdomAllianceDiplomacyContact::query()->create([
                    'alliance_id' => $alliance->id,
                    'tracked_kingdom_alliance_id' => $tracking->id,
                    'kingdom_alliance_id' => $reference->id,
                    'display_name' => 'Diplomat '.$index,
                    'game_role' => 'Diplomat',
                    'channel_type' => 'in_game',
                    'handle' => 'diplomat-'.$index,
                    'state' => 'active',
                    'last_verified_at' => $index % 4 === 0 ? now()->subDays(40) : now()->subDays(5),
                    'created_by_player_id' => $ownerPlayer->id,
                    'updated_by_player_id' => $ownerPlayer->id,
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
        $metrics = $this->app->make(KingdomAllianceIntelligence::class)->forAlliance(
            $alliance,
            true,
            [
                'tracking' => 'active',
                'freshness' => 'all',
                'diplomacy' => 'all',
                'sort' => 'name',
                'direction' => 'asc',
            ],
            now(),
        );
        $collectQueries = false;

        self::assertSame(120, $metrics['summary']['activeTrackedAlliances']);
        self::assertSame(120, $metrics['summary']['observationQuality']['current']);
        self::assertSame(12, $metrics['summary']['relationshipsNeedingReview']);
        self::assertSame(60, $metrics['managerSummary']['trackedWithActiveContact']);
        self::assertSame(30, $metrics['managerSummary']['trackedWithVerificationDue']);
        self::assertCount(120, $metrics['rows']);
        self::assertLessThanOrEqual(
            10,
            $selectQueries,
            'Alliance intelligence must remain batched rather than growing SELECT count with tracking/history volume.',
        );
    }
}
