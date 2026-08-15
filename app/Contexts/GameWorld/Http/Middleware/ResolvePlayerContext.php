<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Http\Middleware;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolvePlayerContext
{
    public function __construct(private PlayerContext $context) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return $next($request);
        }

        $sessionKey = (string) config('identity.active_player_session_key');
        $activePlayerId = $request->session()->get($sessionKey);

        if ($request->session()->exists($sessionKey)) {
            if (! is_string($activePlayerId) || $activePlayerId === '') {
                $request->session()->forget($sessionKey);
                abort(403, 'The selected Player is not available to this account.');
            }

            $player = Player::query()
                ->whereKey($activePlayerId)
                ->where('user_id', $user->id)
                ->with('currentKingdom')
                ->first();

            if (! $player instanceof Player) {
                $request->session()->forget($sessionKey);
                abort(403, 'The selected Player is not available to this account.');
            }

            $this->activate($request, $user, $player);
        } else {
            $owned = Player::query()
                ->where('user_id', $user->id)
                ->with('currentKingdom')
                ->orderBy('id')
                ->limit(2)
                ->get();

            if ($owned->count() === 1) {
                $player = $owned->first();
                if ($player instanceof Player) {
                    $request->session()->put($sessionKey, (string) $player->id);
                    $this->activate($request, $user, $player);
                }
            }
        }

        try {
            return $next($request);
        } finally {
            $request->attributes->remove('active_player_id');
            $this->context->clear();
        }
    }

    private function activate(Request $request, User $user, Player $player): void
    {
        $this->context->activate($player, $user);
        $request->attributes->set('active_player_id', (string) $player->id);
    }
}
