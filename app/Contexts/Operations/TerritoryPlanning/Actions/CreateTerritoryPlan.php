<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanAlliance;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateTerritoryPlan
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AllianceAuthorityFactsQuery $allianceAuthority,
        private KingdomAuthorityFactsQuery $kingdomAuthority,
        private AllianceReferenceQuery $alliances,
        private AllianceOperationsAuthorization $allianceAuthorization,
        private KingdomOperationsAuthorization $kingdomAuthorization,
        private KingdomMapDatasetQuery $datasets,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        string $actorPlayerId,
        TerritoryPlanScope $scope,
        string $kingdomId,
        ?string $ownerAllianceId,
        string $name,
        string $mapDatasetId,
    ): TerritoryPlanMutationReceipt {
        $name = trim($name);
        if ($name === '' || mb_strlen($name) > 160) {
            throw ValidationException::withMessages([
                'name' => 'Plan name is required and must be 160 characters or fewer.',
            ]);
        }
        if (($scope === TerritoryPlanScope::Alliance) !== ($ownerAllianceId !== null && $ownerAllianceId !== '')) {
            throw ValidationException::withMessages([
                'scope' => 'Alliance plans require one owner Alliance; Kingdom plans do not.',
            ]);
        }

        $dataset = $this->datasets->require($mapDatasetId);

        return DB::transaction(function () use (
            $actorPlayerId,
            $scope,
            $kingdomId,
            $ownerAllianceId,
            $name,
            $dataset,
        ): TerritoryPlanMutationReceipt {
            $actor = $this->players->lockCurrent($actorPlayerId);
            if ($actor->kingdomId !== $kingdomId) {
                throw new AuthorizationException;
            }

            $ownerAllianceName = null;
            if ($scope === TerritoryPlanScope::Alliance) {
                $facts = $this->allianceAuthority->lockCurrent($actorPlayerId, (string) $ownerAllianceId);
                if ($facts === null || $facts->kingdomId !== $kingdomId) {
                    throw new AuthorizationException;
                }
                $this->allianceAuthorization->authorizeFacts(
                    $facts,
                    OperationsPermission::TerritoryAllianceManage,
                );
                $alliance = $this->alliances->require((string) $ownerAllianceId);
                if ($alliance->kingdomId !== $kingdomId) {
                    throw new AuthorizationException;
                }
                $ownerAllianceName = $alliance->name;
            } else {
                $facts = $this->kingdomAuthority->lockCurrent($actorPlayerId, $kingdomId);
                if ($facts === null) {
                    throw new AuthorizationException;
                }
                $this->kingdomAuthorization->authorizeFacts(
                    $facts,
                    OperationsPermission::TerritoryKingdomManage,
                );
            }

            $plan = TerritoryPlan::query()->create([
                'kingdom_id' => $kingdomId,
                'owner_alliance_id' => $ownerAllianceId,
                'scope' => $scope,
                'name' => $name,
                'status' => TerritoryPlanStatus::Draft,
                'revision' => 1,
                'map_dataset_id' => $dataset->id,
                'map_dataset_checksum' => $dataset->checksum,
                'planning_preferences' => [],
                'created_by_player_id' => $actorPlayerId,
                'updated_by_player_id' => $actorPlayerId,
            ]);

            if ($scope === TerritoryPlanScope::Alliance) {
                TerritoryPlanAlliance::query()->create([
                    'territory_plan_id' => $plan->id,
                    'plan_key' => 'alliance-'.(string) $ownerAllianceId,
                    'alliance_id' => $ownerAllianceId,
                    'display_name' => $ownerAllianceName,
                    'presentation_color' => '#4da3ff',
                    'sort_order' => 0,
                ]);
            }

            $this->audit->record('territory.plan.created', $actor, $plan, $ownerAllianceId, [
                'scope' => $scope->value,
                'kingdom_id' => $kingdomId,
                'map_dataset_id' => $dataset->id,
                'map_dataset_checksum' => $dataset->checksum,
            ]);

            return new TerritoryPlanMutationReceipt(
                (string) $plan->id,
                1,
                TerritoryPlanStatus::Draft->value,
            );
        });
    }
}
