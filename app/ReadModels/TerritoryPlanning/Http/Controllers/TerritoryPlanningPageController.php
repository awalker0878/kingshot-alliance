<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TerritoryPlanningPageController extends Controller
{
    public function index(
        Request $request,
        PlayerContext $playerContext,
        TerritoryPlanQuery $plans,
        KingdomMapDatasetQuery $datasets,
        PlayerMembershipQuery $memberships,
        AllianceReferenceQuery $alliances,
        AllianceOperationsAuthorization $allianceAuthorization,
        KingdomOperationsAuthorization $kingdomAuthorization,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $playerContext->playerOrNull();
        abort_unless($player !== null, 403);

        $allianceIds = $memberships->activeAllianceIdsForPlayerInKingdom(
            $player->playerId,
            $player->kingdomId,
        );
        $references = $alliances->byIds($allianceIds);
        $allianceOptions = [];
        foreach ($allianceIds as $allianceId) {
            $reference = $references[$allianceId] ?? null;
            if ($reference === null) {
                continue;
            }
            $allianceOptions[] = [
                'id' => $reference->allianceId,
                'name' => $reference->name,
                'canManage' => $allianceAuthorization->allows(
                    $player->playerId,
                    $reference->allianceId,
                    OperationsPermission::TerritoryAllianceManage,
                ),
            ];
        }

        return Inertia::render('Kingdom/Territory/Index', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'kingdomId' => $player->kingdomId,
                'kingdomNumber' => $player->kingdomNumber,
            ],
            'plans' => $plans->visiblePlans($player->playerId, $player->kingdomId),
            'mapDatasets' => array_map(static fn ($dataset): array => [
                'id' => $dataset->id,
                'observedAt' => $dataset->observedAt,
                'sourceLabel' => $dataset->sourceLabel,
                'sourceUri' => $dataset->sourceUri,
                'confidence' => $dataset->confidence->value,
                'checksum' => $dataset->checksum,
            ], $datasets->all()),
            'allianceOptions' => $allianceOptions,
            'canManageKingdomPlans' => $kingdomAuthorization->allows(
                $player->playerId,
                $player->kingdomId,
                OperationsPermission::TerritoryKingdomManage,
            ),
        ]);
    }

    public function show(
        Request $request,
        string $plan,
        PlayerContext $playerContext,
        TerritoryPlanQuery $plans,
        PlayerMembershipQuery $memberships,
        PlayerReferenceQuery $players,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $playerContext->playerOrNull();
        abort_unless($player !== null, 403);

        $territory = $plans->detail($player->playerId, $plan);
        $territory['governor_options'] = $this->governorOptions($territory, $memberships, $players);

        return Inertia::render('Kingdom/Territory/Editor', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'kingdomNumber' => $player->kingdomNumber,
            ],
            'territory' => $territory,
        ]);
    }

    public function alliances(
        Request $request,
        string $plan,
        PlayerContext $playerContext,
        TerritoryPlanQuery $plans,
        AllianceReferenceQuery $alliances,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $playerContext->playerOrNull();
        abort_unless($player !== null, 403);

        $territory = $plans->detail($player->playerId, $plan);
        $planData = $territory['plan'] ?? null;
        abort_unless(is_array($planData) && ($planData['scope'] ?? null) === 'kingdom', 404);
        $kingdomId = $planData['kingdom_id'] ?? null;
        abort_unless(is_string($kingdomId), 404);

        $allianceOptions = array_map(
            static fn ($reference): array => [
                'id' => $reference->allianceId,
                'name' => $reference->name,
            ],
            $alliances->inKingdom($kingdomId, true),
        );
        $objectCounts = [];
        $objects = $territory['objects'] ?? null;
        if (is_array($objects)) {
            foreach ($objects as $object) {
                if (! is_array($object) || ! is_string($object['alliance_key'] ?? null)) {
                    continue;
                }
                $key = $object['alliance_key'];
                $objectCounts[$key] = ($objectCounts[$key] ?? 0) + 1;
            }
        }

        return Inertia::render('Kingdom/Territory/Alliances', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'kingdomNumber' => $player->kingdomNumber,
            ],
            'plan' => $planData,
            'alliances' => $territory['alliances'] ?? [],
            'allianceOptions' => $allianceOptions,
            'objectCounts' => $objectCounts,
        ]);
    }

    /**
     * @param  array<string, mixed>  $territory
     * @return array<string, list<array{id: string, name: string}>>
     */
    private function governorOptions(
        array $territory,
        PlayerMembershipQuery $memberships,
        PlayerReferenceQuery $players,
    ): array {
        $layers = $territory['alliances'] ?? null;
        if (! is_array($layers)) {
            return [];
        }

        $playerIdsByLayer = [];
        $allPlayerIds = [];
        foreach ($layers as $layer) {
            if (! is_array($layer)) {
                continue;
            }
            $layerKey = $layer['key'] ?? null;
            $allianceId = $layer['alliance_id'] ?? null;
            if (! is_string($layerKey) || ! is_string($allianceId)) {
                continue;
            }

            $memberIds = $memberships->activePlayerIds($allianceId);
            $playerIdsByLayer[$layerKey] = $memberIds;
            foreach ($memberIds as $playerId) {
                $allPlayerIds[$playerId] = true;
            }
        }

        $references = $players->byIds(array_keys($allPlayerIds));
        $options = [];
        foreach ($playerIdsByLayer as $layerKey => $playerIds) {
            $layerOptions = [];
            foreach ($playerIds as $playerId) {
                $reference = $references[$playerId] ?? null;
                if ($reference === null) {
                    continue;
                }
                $layerOptions[] = [
                    'id' => $reference->playerId,
                    'name' => $reference->currentName,
                ];
            }
            $options[$layerKey] = $layerOptions;
        }

        return $options;
    }
}
