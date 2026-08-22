<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RestoreTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Services\HiveLayoutGenerator;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanImport;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class TerritoryPlanController extends Controller
{
    public function store(Request $request, PlayerContext $players, CreateTerritoryPlan $create): RedirectResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'scope' => ['required', 'in:alliance,kingdom'], 'kingdom_id' => ['required', 'ulid'],
            'owner_alliance_id' => ['nullable', 'ulid'], 'name' => ['required', 'string', 'max:160'],
            'map_dataset_id' => ['required', 'string', 'max:120'],
        ]);
        $receipt = $create->handle($player->playerId, TerritoryPlanScope::from($data['scope']), $data['kingdom_id'], $data['owner_alliance_id'] ?? null, $data['name'], $data['map_dataset_id']);

        return redirect()->route('territory.show', ['plan' => $receipt->planId])->with('success', 'territory.created');
    }

    public function save(Request $request, string $plan, PlayerContext $players, SaveTerritoryPlan $save): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer', 'min:1'],
            'alliances' => ['required', 'array', 'min:1', 'max:50'], 'groups' => ['present', 'array', 'max:500'],
            'objects' => ['present', 'array', 'max:5000'], 'planning_preferences' => ['sometimes', 'array'],
        ]);
        $receipt = $save->handle($player->playerId, $plan, (int) $data['expected_revision'], $data['alliances'], $data['groups'], $data['objects'], $data['planning_preferences'] ?? []);

        return response()->json(['receipt' => ['plan_id' => $receipt->planId, 'revision' => $receipt->revision, 'status' => $receipt->status]]);
    }

    public function publish(Request $request, string $plan, PlayerContext $players, PublishTerritoryPlan $publish): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        $receipt = $publish->handle($player->playerId, $plan, (int) $data['expected_revision']);

        return response()->json(['receipt' => ['plan_id' => $receipt->planId, 'revision' => $receipt->revision, 'status' => $receipt->status, 'published_revision_id' => $receipt->publishedRevisionId]]);
    }

    public function archive(Request $request, string $plan, PlayerContext $players, ArchiveTerritoryPlan $archive): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        $receipt = $archive->handle($player->playerId, $plan, (int) $data['expected_revision']);

        return response()->json(['receipt' => ['plan_id' => $receipt->planId, 'revision' => $receipt->revision, 'status' => $receipt->status]]);
    }

    public function restore(Request $request, string $plan, string $revision, PlayerContext $players, RestoreTerritoryPlanRevision $restore): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        $receipt = $restore->handle($player->playerId, $plan, $revision, (int) $data['expected_revision']);

        return response()->json(['receipt' => ['plan_id' => $receipt->planId, 'revision' => $receipt->revision, 'status' => $receipt->status]]);
    }

    public function previewImport(Request $request, TerritoryPlanImport $import): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'string', 'max:5000000']]);

        return response()->json(['preview' => $import->preview($data['document'])]);
    }

    public function generateHive(Request $request, HiveLayoutGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'style' => ['required', 'in:swirl,banner_pad'], 'alliance_key' => ['required', 'string', 'max:120'],
            'center_x' => ['required', 'integer', 'between:0,1199'], 'center_y' => ['required', 'integer', 'between:0,1199'],
            'city_count' => ['sometimes', 'integer', 'between:1,100'],
        ]);

        return response()->json(['objects' => $generator->generate($data['style'], $data['alliance_key'], (int) $data['center_x'], (int) $data['center_y'], (int) ($data['city_count'] ?? 50))]);
    }
}
