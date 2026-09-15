<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryRendition;
use App\Contexts\Operations\TerritoryPlanning\Actions\InstantiateTerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryAnnotations;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryArtifactQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryPlanQuery;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryCoordinateTableAdapter;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

final class TerritoryArtifactController extends Controller
{
    public function annotations(
        Request $request,
        string $plan,
        PlayerContext $players,
        SaveTerritoryAnnotations $save,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'expected_revision' => ['required', 'integer:strict', 'min:1'],
            'annotations' => ['present', 'array', 'max:500'],
            'annotations.*' => ['array'],
        ]);

        return $this->privateNoStore(response()->json($save->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['annotations'],
        )));
    }

    public function templates(
        string $plan,
        PlayerContext $players,
        TerritoryArtifactQuery $artifacts,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        return $this->privateNoStore(response()->json([
            'templates' => $artifacts->templates($player->playerId, $plan),
        ]));
    }

    public function saveTemplate(
        Request $request,
        string $plan,
        PlayerContext $players,
        SaveTerritoryHiveTemplate $save,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'style' => ['required', 'in:swirl,banner_pad'],
            'city_count' => ['required', 'integer:strict', 'between:1,100'],
            'spacing' => ['required', 'integer:strict', 'between:0,8'],
            'planning_preferences' => ['present', 'array:preferred_bear_radius_tiles,march_seconds_per_tile,selected_bear_trap_by_alliance'],
        ]);
        $template = $save->handle(
            $player->playerId,
            $plan,
            trim($data['name']),
            $data['style'],
            (int) $data['city_count'],
            (int) $data['spacing'],
            $data['planning_preferences'],
        );

        return $this->privateNoStore(response()->json(['template' => $template]));
    }

    public function instantiateTemplate(
        Request $request,
        string $plan,
        string $template,
        PlayerContext $players,
        InstantiateTerritoryHiveTemplate $instantiate,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'existing_objects' => ['present', 'array', 'max:5000'],
            'existing_objects.*' => ['array'],
            'alliance_key' => ['required', 'string', 'max:120'],
            'center_x' => ['required', 'integer:strict', 'between:-1000000,1000000'],
            'center_y' => ['required', 'integer:strict', 'between:-1000000,1000000'],
        ]);

        return $this->privateNoStore(response()->json($instantiate->handle(
            $player->playerId,
            $plan,
            $template,
            $data['existing_objects'],
            $data['alliance_key'],
            (int) $data['center_x'],
            (int) $data['center_y'],
        )));
    }

    public function renditions(
        string $plan,
        PlayerContext $players,
        TerritoryArtifactQuery $artifacts,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        return $this->privateNoStore(response()->json([
            'renditions' => $artifacts->renditions($player->playerId, $plan),
        ]));
    }

    public function renditionShow(
        string $plan,
        string $rendition,
        PlayerContext $players,
        TerritoryArtifactQuery $artifacts,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        return $this->privateNoStore(response()->json([
            'rendition' => $artifacts->rendition($player->playerId, $plan, $rendition),
        ]));
    }

    public function rendition(
        Request $request,
        string $plan,
        PlayerContext $players,
        CreateTerritoryRendition $create,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'revision_id' => ['required', 'ulid'],
            'scope' => ['required', 'in:world,viewport,alliance,hive'],
            'media_type' => ['required', 'in:image/png,image/svg+xml,application/pdf'],
            'content_base64' => ['required', 'string', 'max:7000000'],
            'metadata' => ['present', 'array'],
        ]);
        $rendition = $create->handle(
            $player->playerId,
            $plan,
            $data['revision_id'],
            $data['scope'],
            $data['media_type'],
            $data['content_base64'],
            $data['metadata'],
        );

        return $this->privateNoStore(response()->json(['rendition' => $rendition]));
    }

    public function coordinateTable(
        string $plan,
        PlayerContext $players,
        TerritoryPlanQuery $plans,
        TerritoryCoordinateTableAdapter $coordinates,
    ): Response {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $detail = $plans->detail($player->playerId, $plan);
        $csv = $coordinates->encode($this->rows($detail['objects'] ?? null));

        return response($csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="territory-coordinates.csv"',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    public function coordinatePreview(
        Request $request,
        string $plan,
        PlayerContext $players,
        TerritoryPlanQuery $plans,
        TerritoryCoordinateTableAdapter $coordinates,
    ): JsonResponse {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);
        $data = $request->validate([
            'csv' => ['required', 'string', 'max:1000000'],
        ]);
        $plans->authorizeView($player->playerId, $plan);
        $rows = $coordinates->decode($data['csv']);

        return $this->privateNoStore(response()->json([
            'rows' => $rows,
            'row_count' => count($rows),
        ]));
    }

    private function privateNoStore(JsonResponse $response): JsonResponse
    {
        return $response->header('Cache-Control', 'private, no-store');
    }

    /** @return list<array<string, mixed>> */
    private function rows(mixed $value): array
    {
        if (! is_array($value) || ! array_is_list($value)) {
            throw new \LogicException('Territory plan detail objects are invalid.');
        }
        $rows = [];
        foreach ($value as $row) {
            if (! is_array($row) || array_is_list($row)) {
                throw new \LogicException('Territory plan detail objects are invalid.');
            }
            $entry = [];
            foreach ($row as $key => $item) {
                if (! is_string($key)) {
                    throw new \LogicException('Territory plan detail objects are invalid.');
                }
                $entry[$key] = $item;
            }
            $rows[] = $entry;
        }

        return $rows;
    }
}
