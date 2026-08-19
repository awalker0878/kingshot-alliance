<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Controllers;

use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Players\Actions\ActivatePlayer;
use App\Contexts\GameWorld\Players\Services\GameRouteRegistry;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

final class ActivatePlayerController extends Controller
{
    public function __invoke(
        Request $request,
        string $player,
        ActivatePlayer $activatePlayer,
        PlayerIdentityContextQuery $identityContext,
        GameRouteRegistry $routes,
    ): RedirectResponse {
        $authId = Auth::id();
        abort_unless(is_numeric($authId), 401);

        $sessionKey = (string) config('game_world.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);

        $target = $activatePlayer->handle(
            (int) $authId,
            $player,
            is_string($previousPlayerId) ? $previousPlayerId : null,
        );

        $request->session()->put($sessionKey, $target->playerId);

        $targetAlliance = $identityContext->forPlayers([$target->playerId])[$target->playerId] ?? null;
        $routeAlliance = $targetAlliance === null ? null : [
            'capabilities' => $targetAlliance['capabilities'],
        ];
        $returnTo = $request->input('returnTo');
        $destination = $routes->resolveSwitchDestination(
            is_string($returnTo) ? $returnTo : null,
            $routeAlliance,
        );

        return redirect()->to($destination);
    }
}
