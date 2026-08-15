<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Shared\Audit\Services\AuditRecorder;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

final class ActivatePlayerController extends Controller
{
    public function __invoke(Request $request, string $player, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $sessionKey = (string) config('game_world.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);

        $target = DB::transaction(function () use ($user, $player, $audit, $previousPlayerId): Player {
            // Ownership mutation/account deletion uses User -> Player. Share-lock the
            // same rows while selecting a new game principal so the activation evidence
            // cannot be recorded for a Player that is being detached concurrently.
            $currentUser = User::query()->whereKey($user->id)->sharedLock()->firstOrFail();
            $currentPlayer = Player::query()
                ->whereKey($player)
                ->where('user_id', $currentUser->id)
                ->sharedLock()
                ->firstOrFail();

            $audit->record('player.context_changed', $currentUser, $currentPlayer, null, [
                'previous_player_id' => is_string($previousPlayerId) ? $previousPlayerId : null,
                'player_id' => (string) $currentPlayer->id,
            ]);

            return $currentPlayer;
        });

        $request->session()->put($sessionKey, (string) $target->id);

        return back();
    }
}
