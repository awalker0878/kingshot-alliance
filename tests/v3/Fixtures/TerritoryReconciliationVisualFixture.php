<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservedObject;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use Carbon\CarbonImmutable;

final class TerritoryReconciliationVisualFixture
{
    public static function seed(): void
    {
        $user = User::query()->where('email', 'territory-visual@example.test')->firstOrFail();
        $player = Player::query()->where('user_id', $user->id)->firstOrFail();
        $plan = TerritoryPlan::query()
            ->where('name', 'Bear Hive Alpha')
            ->where('kingdom_id', $player->current_kingdom_id)
            ->firstOrFail();
        $allianceId = (string) $plan->owner_alliance_id;
        $published = app(PublishTerritoryPlan::class)->handle(
            (string) $player->id,
            (string) $plan->id,
            (int) $plan->revision,
        );
        if ($published->publishedRevisionId !== null) {
            TerritoryPlanRevision::query()->whereKey($published->publishedRevisionId)->update([
                'published_at' => CarbonImmutable::parse('2026-08-27T18:00:00Z'),
            ]);
        }

        $dataset = app(KingdomMapDatasetQuery::class)->require(
            'kingshot-community-observed-2026-08-21-v1',
        );
        $observation = SpatialObservation::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_id' => (string) $player->current_kingdom_id,
            'captured_at' => CarbonImmutable::parse('2026-08-27T17:40:00Z'),
            'coverage_kind' => SpatialObservationCoverageKind::CompleteHive,
            'completeness' => SpatialObservationCompleteness::Complete,
            'coverage_bounds' => null,
            'map_dataset_id' => $dataset->id,
            'map_dataset_checksum' => $dataset->checksum,
            'source' => 'screenshot_evidence',
            'source_evidence_id' => null,
            'source_review_id' => null,
            'destination_idempotency_key' => hash('sha256', 'territory-reconciliation-visual'),
            'accepted_by_player_id' => (string) $player->id,
            'accepted_at' => CarbonImmutable::parse('2026-08-27T17:45:00Z'),
        ]);

        self::object($observation, 'hq-observed', SpatialObservedObjectType::Headquarters, 100, 100);
        self::object($observation, 'banner-observed', SpatialObservedObjectType::Banner, 113, 100);
        self::object(
            $observation,
            'north-star-observed',
            SpatialObservedObjectType::GovernorCity,
            116,
            112,
            'North Star',
            SpatialObservedIdentityState::ResolvedPlanLocal,
        );
        self::object($observation, 'trap-observed', SpatialObservedObjectType::BearTrap, 120, 120);
        self::object(
            $observation,
            'unexpected-city',
            SpatialObservedObjectType::GovernorCity,
            130,
            130,
            null,
            SpatialObservedIdentityState::Unresolved,
            'Unknown Governor',
        );
    }

    private static function object(
        SpatialObservation $observation,
        string $key,
        SpatialObservedObjectType $type,
        int $x,
        int $y,
        ?string $planLocalIdentity = null,
        SpatialObservedIdentityState $identityState = SpatialObservedIdentityState::Unresolved,
        ?string $label = null,
    ): void {
        SpatialObservedObject::query()->create([
            'spatial_observation_id' => $observation->id,
            'object_key' => $key,
            'object_type' => $type,
            'coordinate_x' => $x,
            'coordinate_y' => $y,
            'player_id' => null,
            'plan_local_identity' => $planLocalIdentity,
            'observed_label' => $label ?? $planLocalIdentity,
            'identity_state' => $identityState,
            'confidence' => 0.98,
            'source_metadata' => [],
        ]);
    }
}
