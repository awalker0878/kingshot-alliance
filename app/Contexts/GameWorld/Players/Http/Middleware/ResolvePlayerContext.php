<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class ResolvePlayerContext
{
    public function __construct(
        private PlayerContext $context,
        private PlayerReferenceQuery $players,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return $next($request);
        }

        $userId = (int) $user->id;
        $sessionKey = (string) config('game_world.active_player_session_key');
        $activePlayerId = $request->session()->get($sessionKey);

        if ($request->session()->exists($sessionKey)) {
            if (! is_string($activePlayerId) || $activePlayerId === '') {
                $request->session()->forget($sessionKey);
                abort(403, 'The selected Player is not available to this account.');
            }

            $player = $this->players->findOwnedByUser($userId, $activePlayerId);
            if (! $player instanceof PlayerReference) {
                $request->session()->forget($sessionKey);
                abort(403, 'The selected Player is not available to this account.');
            }

            $this->activate($request, $userId, $player);
        } else {
            $owned = $this->players->ownedByUserUpTo($userId, 2);
            if (count($owned) === 1) {
                $player = $owned[0];
                $request->session()->put($sessionKey, $player->playerId);
                $this->activate($request, $userId, $player);
            }
        }

        try {
            return $next($request);
        } finally {
            $request->attributes->remove('active_player_id');
            $this->context->clear();
        }
    }

    private function activate(Request $request, int $userId, PlayerReference $player): void
    {
        $this->context->activate($player, $userId);
        $request->attributes->set('active_player_id', $player->playerId);
    }
}
