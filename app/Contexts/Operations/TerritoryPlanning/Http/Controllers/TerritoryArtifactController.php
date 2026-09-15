<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryRendition;
use App\Contexts\Operations\TerritoryPlanning\Actions\InstantiateTerritoryHiveTemplate;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryAnnotations;
use App\Contexts\Operations\TerritoryPlanning\Actions\SaveTerritoryHiveTemplate;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

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

        return response()->json($save->handle(
            $player->playerId,
            $plan,
            (int) $data['expected_revision'],
            $data['annotations'],
        ));
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

        return response()->json(['template' => $template->only([
            'id', 'kingdom_id', 'name', 'map_dataset_id', 'map_dataset_checksum',
            'style', 'city_count', 'spacing', 'planning_preferences',
        ])]);
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

        return response()->json($instantiate->handle(
            $player->playerId,
            $plan,
            $template,
            $data['existing_objects'],
            $data['alliance_key'],
            (int) $data['center_x'],
            (int) $data['center_y'],
        ))->header('Cache-Control', 'private, no-store');
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

        return response()->json(['rendition' => $rendition->only([
            'id', 'territory_plan_revision_id', 'scope', 'media_type',
            'content_checksum', 'content_bytes', 'metadata', 'created_at',
        ])]);
    }
}
