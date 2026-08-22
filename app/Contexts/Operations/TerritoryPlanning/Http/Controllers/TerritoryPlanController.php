<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

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
            'expected_revision' => ['required', 'integer', 'min:1'],
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
            'expected_revision' => ['required', 'integer', 'min:1'],
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
            'expected_revision' => ['required', 'integer', 'min:1'],
            'document' => ['required', 'string', 'max:5000000'],
        ]);
        $mutation = $import->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['document'],
        );

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function publish(Request $request, string $plan, PlayerContext $players, PublishTerritoryPlan $publish): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
        $mutation = $publish->handle($player->playerId, $plan, (int) $data['expected_revision']);

        return response()->json(['receipt' => $this->mutationReceipt($mutation)]);
    }

    public function archive(Request $request, string $plan, PlayerContext $players, ArchiveTerritoryPlan $archive): JsonResponse
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
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

        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1']]);
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

    public function generateHive(Request $request, HiveLayoutGenerator $generator): JsonResponse
    {
        $data = $request->validate([
            'style' => ['required', 'in:swirl,banner_pad'],
            'alliance_key' => ['required', 'string', 'max:120'],
            'center_x' => ['required', 'integer'],
            'center_y' => ['required', 'integer'],
            'city_count' => ['sometimes', 'integer', 'between:1,100'],
        ]);
        $objects = $generator->generate(
            $data['style'],
            $data['alliance_key'],
            (int) $data['center_x'],
            (int) $data['center_y'],
            (int) ($data['city_count'] ?? 50),
        );

        return response()->json(['objects' => $objects]);
    }

    /** @return array{plan_id: string, revision: int, status: string, published_revision_id: ?string} */
    private function mutationReceipt(TerritoryPlanMutationReceipt $mutation): array
    {
        return [
            'plan_id' => $mutation->planId,
            'revision' => $mutation->revision,
            'status' => $mutation->status,
            'published_revision_id' => $mutation->publishedRevisionId,
        ];
    }
}
