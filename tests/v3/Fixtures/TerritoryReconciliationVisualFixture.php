<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedIdentityState;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservedObjectType;
use App\Contexts\Intelligence\Observations\Models\SpatialObservation;
use App\Contexts\Intelligence\Observations\Models\SpatialObservedObject;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;

final class TerritoryReconciliationVisualFixture
{
    public static function seed(): void
    {
        $user = User::factory()->create([
            'name' => 'Territory Reconciliation Visual',
            'email' => 'territory-reconciliation-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdom = Kingdom::query()->create(['number' => 1337, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'GOV-TERRITORY-RECON',
            'current_name' => 'Hive Marshal',
        ]);
        $allianceId = app(CreateAlliance::class)->handle(
            (string) $player->id,
            'Dawn Recon',
            'dawn-recon',
            'en',
            'UTC',
        );
        $plan = app(CreateTerritoryPlan::class)->handle(
            (string) $player->id,
            TerritoryPlanScope::Alliance,
            (string) $kingdom->id,
            $allianceId,
            'Observed Hive Alpha',
            'kingshot-community-observed-2026-08-21-v1',
        );
        app(SaveTerritoryPlan::class)->handle(
            (string) $player->id,
            $plan->planId,
            $plan->revision,
            [[
                'key' => 'owner',
                'alliance_id' => $allianceId,
                'external_name' => null,
                'external_tag' => null,
                'display_name' => 'Dawn Recon',
                'presentation_color' => '#4da3ff',
                'sort_order' => 0,
                'visible' => true,
                'locked' => false,
            ]],
            [['key' => 'hive', 'label' => 'Primary Hive']],
            [
                ['key' => 'hq', 'alliance_key' => 'owner', 'group_key' => null, 'type' => 'headquarters', 'player_id' => null, 'external_player_name' => null, 'label' => 'HQ', 'x' => 100, 'y' => 100, 'rotation' => 0, 'sort_order' => 0, 'metadata' => []],
                ['key' => 'banner', 'alliance_key' => 'owner', 'group_key' => null, 'type' => 'banner', 'player_id' => null, 'external_player_name' => null, 'label' => 'Banner', 'x' => 110, 'y' => 100, 'rotation' => 0, 'sort_order' => 1, 'metadata' => []],
                ['key' => 'city', 'alliance_key' => 'owner', 'group_key' => 'hive', 'type' => 'governor_city', 'player_id' => null, 'external_player_name' => 'North Star', 'label' => 'North Star', 'x' => 112, 'y' => 112, 'rotation' => 0, 'sort_order' => 2, 'metadata' => []],
                ['key' => 'trap-one', 'alliance_key' => 'owner', 'group_key' => 'hive', 'type' => 'bear_trap', 'player_id' => null, 'external_player_name' => null, 'label' => 'Bear Trap 1', 'x' => 120, 'y' => 120, 'rotation' => 0, 'sort_order' => 3, 'metadata' => []],
            ],
            [
                'preferred_bear_radius_tiles' => 40,
                'march_seconds_per_tile' => 2,
                'selected_bear_trap_by_alliance' => ['owner' => 'trap-one'],
            ],
        );
        $savedPlan = TerritoryPlan::query()
            ->findOrFail($plan->planId);
        $published = app(PublishTerritoryPlan::class)->handle(
            (string) $player->id,
            $plan->planId,
            (int) $savedPlan->revision,
        );
        if ($published->publishedRevisionId !== null) {
            TerritoryPlanRevision::query()
                ->whereKey($published->publishedRevisionId)
                ->update([
                    'published_at' => CarbonImmutable::parse('2026-08-27T18:00:00Z'),
                ]);
        }

        $dataset = app(KingdomMapDatasetQuery::class)->require(
            'kingshot-community-observed-2026-08-21-v1',
        );
        $observation = SpatialObservation::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_id' => (string) $kingdom->id,
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
