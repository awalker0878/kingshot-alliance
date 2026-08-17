<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Controllers;

use App\Contexts\GameWorld\Players\Actions\ActivatePlayer;
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

        return back();
    }
}
