<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdom;
use App\Contexts\GameWorld\Players\Actions\CreatePlayerForAccount;
use App\Contexts\GameWorld\Players\Actions\MoveOwnedPlayerToKingdom;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayerAccount;
use App\Contexts\GameWorld\Players\Actions\UpdateOwnedPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Queries\PlayerIdentityHistoryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerLifecyclePolicy;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GovernorManagementController extends Controller
{
    public function index(
        Request $request,
        PlayerReferenceQuery $players,
        PlayerIdentityContextQuery $allianceContext,
        PlayerIdentityHistoryQuery $history,
        PlayerLifecyclePolicy $lifecycle,
    ): Response {
        $account = $this->account($request);
        $userId = (int) $account->getAuthIdentifier();
        $owned = $players->ownedByUser($userId);
        $ids = array_values(array_map(static fn ($player): string => $player->playerId, $owned));
        $alliances = $allianceContext->forPlayers($ids);

        $governors = array_values(array_map(function ($player) use ($alliances, $history, $lifecycle): array {
            $alliance = $alliances[$player->playerId] ?? null;
            $model = Player::query()->whereKey($player->playerId)->whereNull('canonical_player_id')->firstOrFail();

            return [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomId' => $player->kingdomId,
                'kingdomNumber' => $player->kingdomNumber,
                'alliance' => $alliance === null ? null : [
                    'id' => $alliance['allianceId'],
                    'name' => $alliance['allianceName'],
                    'rank' => $alliance['rank'],
                    'roles' => $alliance['roles'],
                ],
                'releaseBlockers' => $lifecycle->releaseBlockers($model),
                'history' => $history->forPlayer($player->playerId, 12),
            ];
        }, $owned));

        $sessionKey = (string) config('game_world.active_player_session_key');
        $activePlayerId = $request->session()->get($sessionKey);

        return Inertia::render('Accounts/Governor/Governors', [
            'user' => [
                'name' => $account->accountName(),
                'email' => $account->accountEmail(),
            ],
            'activePlayerId' => is_string($activePlayerId) ? $activePlayerId : null,
            'governors' => $governors,
        ]);
    }

    public function store(
        Request $request,
        ResolveKingdom $resolveKingdom,
        CreatePlayerForAccount $create,
    ): RedirectResponse {
        $account = $this->account($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'kingdom_number' => ['required', 'integer', 'min:1', 'max:2147483647'],
            'game_player_id' => ['nullable', 'string', 'max:100'],
        ]);
        $kingdom = $resolveKingdom->handle((int) $validated['kingdom_number']);
        abort_if($kingdom === null, 422);
        $player = $create->handle(
            (int) $account->getAuthIdentifier(),
            $kingdom->kingdomId,
            (string) $validated['name'],
            isset($validated['game_player_id']) ? (string) $validated['game_player_id'] : null,
        );

        $sessionKey = (string) config('game_world.active_player_session_key');
        if (! $request->session()->exists($sessionKey)) {
            $request->session()->put($sessionKey, $player->playerId);
        }

        return redirect()->route('governors.index')->with('actionReceipt', $this->receipt('governor-created'));
    }

    public function update(
        Request $request,
        string $player,
        UpdateOwnedPlayerIdentity $update,
    ): RedirectResponse {
        $account = $this->account($request);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:160'],
            'game_player_id' => ['nullable', 'string', 'max:100'],
        ]);
        $update->handle(
            (int) $account->getAuthIdentifier(),
            $player,
            (string) $validated['name'],
            isset($validated['game_player_id']) ? (string) $validated['game_player_id'] : null,
        );

        return redirect()->route('governors.index')->with('actionReceipt', $this->receipt('governor-updated'));
    }

    public function move(
        Request $request,
        string $player,
        ResolveKingdom $resolveKingdom,
        MoveOwnedPlayerToKingdom $move,
    ): RedirectResponse {
        $account = $this->account($request);
        $validated = $request->validate([
            'kingdom_number' => ['required', 'integer', 'min:1', 'max:2147483647'],
        ]);
        $kingdom = $resolveKingdom->handle((int) $validated['kingdom_number']);
        abort_if($kingdom === null, 422);
        $move->handle((int) $account->getAuthIdentifier(), $player, $kingdom->kingdomId);

        return redirect()->route('governors.index')->with('actionReceipt', $this->receipt('governor-moved'));
    }

    public function release(
        Request $request,
        string $player,
        ReleasePlayerAccount $release,
    ): RedirectResponse {
        $account = $this->account($request);
        $release->handle((int) $account->getAuthIdentifier(), $player);

        $sessionKey = (string) config('game_world.active_player_session_key');
        if ($request->session()->get($sessionKey) === $player) {
            $request->session()->forget($sessionKey);
        }

        return redirect()->route('governors.index')->with('actionReceipt', $this->receipt('governor-released'));
    }

    private function account(Request $request): AuthenticatedAccount
    {
        $account = $request->user();
        abort_unless($account instanceof AuthenticatedAccount, 401);

        return $account;
    }
}
