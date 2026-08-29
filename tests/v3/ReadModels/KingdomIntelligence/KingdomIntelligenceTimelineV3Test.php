<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\KingdomIntelligence;

use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\ReadModels\KingdomIntelligence\Queries\KingdomIntelligenceTimelineQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomIntelligenceTimelineV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_timeline_is_bounded_ordered_and_preserves_owner_provenance(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78401);
        $alliance = $scenario->alliance($actor);
        $trackingId = app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => 'Timeline Watch',
                'current_tag' => 'TL',
                'game_alliance_id' => 'timeline-watch',
            ],
        );
        $record = app(RecordKingdomAllianceObservation::class);
        $olderId = $record->handle($alliance->allianceId, $actor->playerId, $trackingId, [
            'observed_name' => 'Timeline Watch',
            'observed_tag' => 'TL',
            'power' => '100000000',
            'member_count' => 50,
            'captured_at' => now()->subHours(2)->toIso8601String(),
        ]);
        $newerId = $record->handle($alliance->allianceId, $actor->playerId, $trackingId, [
            'observed_name' => 'Timeline Watch',
            'observed_tag' => 'TL',
            'power' => '150000000',
            'member_count' => 55,
            'captured_at' => now()->subHour()->toIso8601String(),
        ]);

        $timeline = app(KingdomIntelligenceTimelineQuery::class)->forTrackedAlliance(
            $actor->playerId,
            $alliance->allianceId,
            $trackingId,
        );

        self::assertLessThanOrEqual(200, count($timeline));
        self::assertSame($timeline, collect($timeline)->sort(static function (array $left, array $right): int {
            $date = strcmp((string) $right['observedAt'], (string) $left['observedAt']);

            return $date !== 0 ? $date : strcmp((string) $left['id'], (string) $right['id']);
        })->values()->all());

        $newer = collect($timeline)->firstWhere('id', 'observation:'.$newerId);
        self::assertIsArray($newer);
        self::assertSame('Intelligence/Observations', $newer['owner']);
        self::assertSame('tracked_alliance', $newer['scope']['type']);
        self::assertSame('/alliance/kingdom-alliances/'.$trackingId.'/history', $newer['canonicalUrl']);
        self::assertArrayHasKey('source', $newer);
        self::assertArrayHasKey('confidence', $newer);
        self::assertNotNull(collect($timeline)->firstWhere('id', 'observation:'.$olderId));
    }

    public function test_timeline_authorizes_before_owner_queries(): void
    {
        $scenario = new ScenarioFactory;
        $ownerAccount = $scenario->account();
        $owner = $scenario->player($ownerAccount->userId, 78402);
        $alliance = $scenario->alliance($owner);

        $otherAccount = $scenario->account();
        $other = $scenario->player($otherAccount->userId, 78403);
        $scenario->alliance($other);

        $this->expectException(AuthorizationException::class);
        app(KingdomIntelligenceTimelineQuery::class)->forTrackedAlliance(
            $other->playerId,
            $alliance->allianceId,
            '01J00000000000000000000000',
        );
    }
}
