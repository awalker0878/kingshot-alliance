<?php

declare(strict_types=1);

namespace App\ReadModels\TerritoryPlanning\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TerritoryPlanningPageController extends Controller
{
    public function index(Request $request, PlayerContext $playerContext, TerritoryPlanQuery $plans, KingdomMapDatasetQuery $datasets): Response
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $playerContext->playerOrNull();
        abort_unless($player !== null, 403);

        return Inertia::render('Kingdom/Territory/Index', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => ['id' => $player->playerId, 'name' => $player->currentName, 'kingdomNumber' => $player->kingdomNumber],
            'plans' => $plans->visiblePlans($player->playerId, $player->kingdomId),
            'mapDatasets' => array_map(static fn ($dataset): array => [
                'id' => $dataset->id, 'observedAt' => $dataset->observedAt, 'sourceLabel' => $dataset->sourceLabel,
                'sourceUri' => $dataset->sourceUri, 'confidence' => $dataset->confidence->value, 'checksum' => $dataset->checksum,
            ], $datasets->all()),
        ]);
    }

    public function show(Request $request, string $plan, PlayerContext $playerContext, TerritoryPlanQuery $plans): Response
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $player = $playerContext->playerOrNull();
        abort_unless($player !== null, 403);

        return Inertia::render('Kingdom/Territory/Editor', [
            'user' => ['name' => $user->name, 'email' => $user->email],
            'activePlayer' => ['id' => $player->playerId, 'name' => $player->currentName, 'kingdomNumber' => $player->kingdomNumber],
            'territory' => $plans->detail($player->playerId, $plan),
        ]);
    }
}
