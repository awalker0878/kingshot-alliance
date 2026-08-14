<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Controllers;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class ActivatePlayerController extends Controller
{
    public function __invoke(Request $request, string $player, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        // The route identifier is scoped by authoritative ownership. A Player owned by
        // another User is intentionally indistinguishable from a nonexistent Player.
        $target = Player::query()
            ->whereKey($player)
            ->where('user_id', $user->id)
            ->firstOrFail();

        $sessionKey = (string) config('identity.active_player_session_key');
        $previousPlayerId = $request->session()->get($sessionKey);
        $request->session()->put($sessionKey, (string) $target->id);

        $audit->record('player.context_changed', $user, $target, null, [
            'previous_player_id' => is_string($previousPlayerId) ? $previousPlayerId : null,
            'player_id' => (string) $target->id,
        ]);

        return back();
    }
}
