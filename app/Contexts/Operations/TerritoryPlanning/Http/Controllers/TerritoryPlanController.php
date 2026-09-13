<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\ArchiveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\AttachTerritoryPlanRevisionToEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\CloneTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\DetachTerritoryPlanRevisionFromEvent;
use App\Contexts\Operations\TerritoryPlanning\Actions\ImportTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\PublishTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RestoreTerritoryPlanRevision;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\UpdateTerritoryPlanAlliances;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanRevisionQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\HiveLayoutGenerator;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryPlanImport;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritorySuggestionGenerator;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationReceipt;
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
            'scope' => ['required', 'in:alliance,kingdom'],
            'kingdom_id' => ['required', 'ulid'],
            'owner_alliance_id' => ['nullable', 'ulid'],
            'name' => ['required', 'string', 'max:160'],
            'map_dataset_id' => ['required', 'string', 'max:120'],
        ]);

        $mutation = $create->handle(
            $player->playerId,
            TerritoryPlanScope::from($data['scope']),
            $data['kingdom_id'],
            $data['owner_alliance_id'] ?? null,
            $data['name'],
            $data['map_dataset_id'],
        );

        return redirect()
            ->route('territory.show', ['plan' => $mutation->planId])
            ->with('success', 'territory.created');
    }

    public function save(Request $request, string $plan, PlayerContext $players, SaveTerritoryPlan $save): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate([
            'expected_revision' => ['required', 'integer:strict', 'min:1'],
            'alliances' => ['required', 'array', 'min:1', 'max:50'],
            'groups' => ['present', 'array', 'max:500'],
            'objects' => ['present', 'array', 'max:5000'],
            'planning_preferences' => ['sometimes', 'array'],
        ]);

        $mutation = $save->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['alliances'],
            $data['groups'],
            $data['objects'],
            $data['planning_preferences'] ?? [],
        );

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function updateAlliances(
        Request $request,
        string $plan,
        PlayerContext $players,
        UpdateTerritoryPlanAlliances $update,
    ): RedirectResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate([
            'expected_revision' => ['required', 'integer:strict', 'min:1'],
            'alliances' => ['required', 'array', 'min:1', 'max:50'],
        ]);
        $update->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['alliances'],
        );

        return redirect()
            ->route('territory.alliances', ['plan' => $plan])
            ->with('success', 'territory.saved');
    }

    public function import(
        Request $request,
        string $plan,
        PlayerContext $players,
        ImportTerritoryPlan $import,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate([
            'expected_revision' => ['required', 'integer:strict', 'min:1'],
            'document' => ['required', 'string', 'max:5000000'],
            'document_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
        ]);
        $mutation = $import->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['document'],
            $data['document_checksum'],
        );

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function publish(Request $request, string $plan, PlayerContext $players, PublishTerritoryPlan $publish): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['expected_revision' => ['required', 'integer:strict', 'min:1'], 'layout_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/']]);
        $mutation = $publish->handle($player->playerId, $plan, $data['expected_revision'], $data['layout_checksum']);

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function archive(Request $request, string $plan, PlayerContext $players, ArchiveTerritoryPlan $archive): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['expected_revision' => ['required', 'integer:strict', 'min:1']]);
        $mutation = $archive->handle($player->playerId, $plan, (int) $data['expected_revision']);

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function restore(
        Request $request,
        string $plan,
        string $revision,
        PlayerContext $players,
        RestoreTerritoryPlanRevision $restore,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['expected_revision' => ['required', 'integer:strict', 'min:1']]);
        $mutation = $restore->handle(
            $player->playerId,
            $plan,
            $revision,
            (int) $data['expected_revision'],
        );

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function clone(Request $request, string $plan, PlayerContext $players, CloneTerritoryPlan $clone): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['name' => ['required', 'string', 'max:160']]);
        $mutation = $clone->handle($player->playerId, $plan, $data['name']);

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function revision(
        string $plan,
        string $revision,
        PlayerContext $players,
        TerritoryPlanRevisionQuery $revisions,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        return response()->json([
            'revision' => $revisions->snapshot($player->playerId, $plan, $revision),
        ]);
    }

    public function attachEvent(
        Request $request,
        string $occurrence,
        PlayerContext $players,
        AttachTerritoryPlanRevisionToEvent $attach,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate([
            'territory_plan_revision_id' => ['required', 'ulid'],
            'purpose' => ['sometimes', 'string', 'max:40'],
        ]);
        $linkId = $attach->handle(
            $player->playerId,
            $occurrence,
            $data['territory_plan_revision_id'],
            $data['purpose'] ?? 'positioning',
        );

        return response()->json(['link_id' => $linkId]);
    }

    public function detachEvent(
        Request $request,
        string $occurrence,
        PlayerContext $players,
        DetachTerritoryPlanRevisionFromEvent $detach,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate([
            'purpose' => ['sometimes', 'string', 'max:40'],
        ]);
        $detached = $detach->handle(
            $player->playerId,
            $occurrence,
            $data['purpose'] ?? 'positioning',
        );

        return response()->json(['detached' => $detached]);
    }

    public function previewImport(Request $request, TerritoryPlanImport $import): JsonResponse
    {
        $data = $request->validate(['document' => ['required', 'string', 'max:5000000']]);

        return response()->json(['preview' => $import->preview($data['document'])]);
    }

    public function generateHive(
        Request $request,
        PlayerContext $players,
        HiveLayoutGenerator $generator,
        TerritorySuggestionGenerator $suggestions,
        KingdomMapDatasetQuery $datasets,
    ): JsonResponse {
        abort_unless($players->playerOrNull() !== null, 403);
        $data = $request->validate([
            'map_dataset_id' => ['required', 'string', 'max:120'],
            'map_dataset_checksum' => ['required', 'string', 'regex:/^[a-f0-9]{64}$/'],
            'existing_objects' => ['present', 'array', 'max:5000'],
            'existing_objects.*' => ['array'],
            'existing_objects.*.key' => ['required', 'string', 'max:120', 'distinct'],
            'existing_objects.*.type' => ['required', 'in:headquarters,banner,governor_city,bear_trap'],
            'existing_objects.*.alliance_key' => ['required', 'string', 'max:120'],
            'existing_objects.*.x' => ['required', 'integer'],
            'existing_objects.*.y' => ['required', 'integer'],
            'existing_objects.*.rotation' => ['required', 'integer', 'in:0,90,180,270'],
            'style' => ['required', 'in:swirl,banner_pad'],
            'alliance_key' => ['required', 'string', 'max:120'],
            'center_x' => ['required', 'integer'],
            'center_y' => ['required', 'integer'],
            'city_count' => ['required', 'integer', 'between:1,100'],
            'spacing' => ['required', 'integer', 'between:0,8'],
            'planning_preferences' => ['present', 'array:preferred_bear_radius_tiles,march_seconds_per_tile,selected_bear_trap_by_alliance'],
            'compare' => ['sometimes', 'boolean'],
        ]);
        $dataset = $datasets->require($data['map_dataset_id'], $data['map_dataset_checksum']);
        $arguments = [$dataset, $data['existing_objects'], $data['alliance_key'], (int) $data['center_x'], (int) $data['center_y'], (int) $data['city_count'], (int) $data['spacing'], $data['planning_preferences']];
        $preview = ($data['compare'] ?? false)
            ? $suggestions->compare(...$arguments)
            : $generator->preview($dataset, $data['existing_objects'], $data['style'], $data['alliance_key'], (int) $data['center_x'], (int) $data['center_y'], (int) $data['city_count'], (int) $data['spacing'], $data['planning_preferences']);

        return response()->json($preview)->header('Cache-Control', 'private, no-store');
    }

    /** @return array<string, mixed> */
    private function mutationReceipt(TerritoryPlanMutationReceipt $mutation): array
    {
        return [
            'plan_id' => $mutation->planId,
            'revision' => $mutation->revision,
            'status' => $mutation->status,
            'published_revision_id' => $mutation->publishedRevisionId,
            'snapshot' => $mutation->snapshot,
            'layout_checksum' => $mutation->layoutChecksum,
        ];
    }
}
