<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Controllers;

use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Players\Actions\ActivatePlayer;
use App\Contexts\GameWorld\Players\Services\PlayerSwitchRouteResolver;
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
        PlayerSwitchRouteResolver $routeResolver,
    ): RedirectResponse {
        $authId = Auth::id();
        abort_unless(is_numeric($authId), 401);

        $sessionKey = (string) config('game_world.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);

        // Ownership and activation are server-authoritative. The browser only requests
        // a Player id; it never supplies Alliance, Kingdom, rank, role, or capability authority.
        $target = $activatePlayer->handle(
            (int) $authId,
            $player,
            is_string($previousPlayerId) ? $previousPlayerId : null,
        );

        $request->session()->put($sessionKey, $target->playerId);

        // The browser may offer the current path as a UX hint, but the server owns
        // reconciliation. Only stable routes valid for the target Player context are
        // retained; resource-specific or invalid paths collapse to a safe parent.
        $targetAlliance = $identityContext->forPlayers([$target->playerId])[$target->playerId] ?? null;
        $routeContext = $targetAlliance === null ? null : [
            'capabilities' => $targetAlliance['capabilities'],
        ];
        $requestedPath = $request->input('returnTo');
        $destination = $routeResolver->resolve(
            is_string($requestedPath) ? $requestedPath : null,
            $routeContext,
        );

        return redirect()->to($destination);
    }
}
