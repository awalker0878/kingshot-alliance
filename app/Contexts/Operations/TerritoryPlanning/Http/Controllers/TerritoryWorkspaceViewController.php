<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryWorkspaceViews;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryWorkspaceViewsQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TerritoryWorkspaceViewController extends Controller
{
    public function show(Request $request, PlayerContext $context, TerritoryWorkspaceViewsQuery $views): JsonResponse
    {
        $player = $context->playerOrNull();
        abort_unless($player !== null && $request->user() !== null, 403);
        $input = $request->validate($this->mapRules());

        return response()->json($views->forPlayer((int) $request->user()->getAuthIdentifier(), $player->playerId, $player->kingdomId, $input['map_dataset_id'], $input['map_dataset_checksum']))
            ->header('Cache-Control', 'private, no-store');
    }

    public function update(Request $request, PlayerContext $context, SaveTerritoryWorkspaceViews $save): JsonResponse
    {
        $player = $context->playerOrNull();
        abort_unless($player !== null && $request->user() !== null, 403);
        $input = $request->validate($this->mapRules() + [
            'expected_revision' => ['required', 'integer', 'min:0'],
            'views' => ['present', 'array', 'max:20'],
        ]);

        return response()->json($save->handle((int) $request->user()->getAuthIdentifier(), $player->playerId, $player->kingdomId, $input['map_dataset_id'], $input['map_dataset_checksum'], (int) $input['expected_revision'], $input['views']))
            ->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, list<string>> */
    private function mapRules(): array
    {
        return [
            'map_dataset_id' => ['required', 'string', 'max:120'],
            'map_dataset_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ];
    }
}
