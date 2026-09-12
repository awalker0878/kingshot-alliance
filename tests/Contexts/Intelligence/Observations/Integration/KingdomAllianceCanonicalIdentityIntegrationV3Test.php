<?php

declare(strict_types=1);

namespace Tests\Contexts\Intelligence\Observations\Integration;

use App\Contexts\GameWorld\Kingdoms\Actions\ReconcileKingdomAlliances;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceIdentitySource;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceIdentityHistoryQuery;
use App\Contexts\Intelligence\Observations\Actions\RecordKingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Actions\StartTrackingKingdomAlliance;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomAllianceCanonicalIdentityIntegrationV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_active_tracking_survives_reconciliation_and_new_observations_use_canonical_identity(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 16150);
        $alliance = $scenario->alliance($actor);

        $trackingId = app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => 'Canonical Watch',
                'current_tag' => 'CW',
                'game_alliance_id' => 'canonical-watch-stable',
            ],
        );
        $tracking = TrackedKingdomAlliance::query()->findOrFail($trackingId);
        $aliasId = (string) $tracking->kingdom_alliance_id;

        $canonical = app(ResolveKingdomAlliance::class)->handle(
            $alliance->kingdomId,
            'Canonical Watch',
            'CW',
            null,
        );
        app(ReconcileKingdomAlliances::class)->handle(
            $canonical->kingdomAllianceId,
            $aliasId,
            'Stable identity confirms the tracked row is an earlier alias.',
        );

        $observationId = app(RecordKingdomAllianceObservation::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            $trackingId,
            [
                'observed_name' => 'Canonical Watch Renamed',
                'observed_tag' => 'CWR',
                'captured_at' => now()->subMinute()->toIso8601String(),
            ],
        );
        $observation = KingdomAllianceObservation::query()->findOrFail($observationId);
        self::assertSame($canonical->kingdomAllianceId, (string) $observation->kingdom_alliance_id);

        $currentIdentity = app(KingdomAllianceIdentityHistoryQuery::class)->currentIdentity($canonical->kingdomAllianceId);
        self::assertNotNull($currentIdentity);
        self::assertSame('Canonical Watch Renamed', $currentIdentity->name);
        self::assertSame(KingdomAllianceIdentitySource::IntelligenceObservation, $currentIdentity->sourceType);
        self::assertSame('observation:'.$observationId, $currentIdentity->sourceReference);

        $this->expectException(ValidationException::class);
        app(StartTrackingKingdomAlliance::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            [
                'current_name' => 'Canonical Watch Renamed',
                'current_tag' => 'CWR',
                'game_alliance_id' => 'canonical-watch-stable',
            ],
        );
    }
}
