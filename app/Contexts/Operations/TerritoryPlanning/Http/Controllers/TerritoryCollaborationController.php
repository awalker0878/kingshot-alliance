<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Http\Controllers;

use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\TerritoryPlanning\Actions\CommentOnTerritoryObject;
use App\Contexts\Operations\TerritoryPlanning\Actions\CreateTerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Actions\GrantTerritoryPlanAccess;
use App\Contexts\Operations\TerritoryPlanning\Actions\ReviewTerritoryPlan;
use App\Contexts\Operations\TerritoryPlanning\Actions\RevokeTerritoryPlanAccess;
use App\Contexts\Operations\TerritoryPlanning\Actions\RevokeTerritoryShare;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritoryCollaborationQuery;
use App\Contexts\Operations\TerritoryPlanning\Queries\TerritorySharedRevisionQuery;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class TerritoryCollaborationController extends Controller
{
    public function overview(Request $request, string $plan, PlayerContext $players, TerritoryCollaborationQuery $query): JsonResponse
    {
        $data = $request->validate(['after' => ['nullable', 'ulid'], 'review_before' => ['nullable', 'ulid'], 'grant_after' => ['nullable', 'ulid'], 'share_before' => ['nullable', 'ulid']]);

        return $this->privateJson($query->get($this->actor($players), $plan, $data['after'] ?? null, $data['review_before'] ?? null, $data['grant_after'] ?? null, $data['share_before'] ?? null));
    }

    public function comment(Request $request, string $plan, PlayerContext $players, CommentOnTerritoryObject $action): JsonResponse
    {
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1'], 'object_key' => ['required', 'string', 'max:120'], 'body' => ['required', 'string', 'max:4000']]);

        return $this->privateJson(['comment_id' => $action->handle($this->actor($players), $plan, (int) $data['expected_revision'], $data['object_key'], $data['body'])]);
    }

    public function review(Request $request, string $plan, PlayerContext $players, ReviewTerritoryPlan $action): JsonResponse
    {
        $data = $request->validate(['expected_revision' => ['required', 'integer', 'min:1'], 'snapshot_checksum' => ['required', 'string', 'size:64'], 'decision' => ['required', 'in:approved,changes_requested'], 'note' => ['nullable', 'string', 'max:4000']]);

        return $this->privateJson(['review_id' => $action->handle($this->actor($players), $plan, (int) $data['expected_revision'], $data['snapshot_checksum'], $data['decision'], $data['note'] ?? null)]);
    }

    public function grant(Request $request, string $plan, PlayerContext $players, GrantTerritoryPlanAccess $action): JsonResponse
    {
        $data = $request->validate(['player_id' => ['required', 'ulid'], 'alliance_key' => ['required', 'string', 'max:120'], 'permission' => ['required', 'in:review,edit'], 'expires_at' => ['required', 'date']]);

        return $this->privateJson(['grant_id' => $action->handle($this->actor($players), $plan, $data['player_id'], $data['alliance_key'], $data['permission'], CarbonImmutable::parse($data['expires_at']))]);
    }

    public function revokeGrant(string $plan, string $grant, PlayerContext $players, RevokeTerritoryPlanAccess $action): JsonResponse
    {
        $action->handle($this->actor($players), $plan, $grant);

        return $this->privateJson(['revoked' => true]);
    }

    public function share(Request $request, string $plan, PlayerContext $players, CreateTerritoryShare $action): JsonResponse
    {
        $data = $request->validate(['revision_id' => ['required', 'ulid'], 'recipient_player_id' => ['required', 'ulid'], 'alliance_keys' => ['required', 'array', 'min:1', 'max:50'], 'alliance_keys.*' => ['required', 'string', 'max:120', 'distinct'], 'expires_at' => ['required', 'date']]);

        return $this->privateJson($action->handle($this->actor($players), $plan, $data['revision_id'], $data['recipient_player_id'], $data['alliance_keys'], CarbonImmutable::parse($data['expires_at'])));
    }

    public function revokeShare(string $plan, string $share, PlayerContext $players, RevokeTerritoryShare $action): JsonResponse
    {
        $action->handle($this->actor($players), $plan, $share);

        return $this->privateJson(['revoked' => true]);
    }

    public function shared(Request $request, string $share, PlayerContext $players, TerritorySharedRevisionQuery $query): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'size:64']]);

        return $this->privateJson($query->get($this->actor($players), $share, $data['token']));
    }

    private function actor(PlayerContext $players): string
    {
        $player = $players->playerOrNull();
        abort_unless($player !== null, 403);

        return $player->playerId;
    }

    /** @param array<string,mixed> $data */
    private function privateJson(array $data): JsonResponse
    {
        return response()->json($data)->header('Cache-Control', 'private, no-store')->header('Referrer-Policy', 'no-referrer');
    }
}
