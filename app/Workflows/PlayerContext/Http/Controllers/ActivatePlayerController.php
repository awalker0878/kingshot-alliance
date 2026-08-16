<?php

declare(strict_types=1);

namespace App\Workflows\PlayerContext\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\PlayerContext\Actions\ActivatePlayer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActivatePlayerController extends Controller
{
    public function __invoke(
        Request $request,
        string $player,
        ActivatePlayer $activatePlayer,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = (string) config('game_world.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);
        $target = $activatePlayer->handle(
            $user,
            $player,
            is_string($previousPlayerId) ? $previousPlayerId : null,
        );

        $request->session()->put($sessionKey, (string) $target->id);

        return back();
    }
}
