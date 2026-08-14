<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Http\Middleware;

use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
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

        if (is_string($activePlayerId) && $activePlayerId !== '') {
            $player = Player::query()
                ->whereKey($activePlayerId)
                ->where('user_id', $user->id)
                ->with('currentKingdom')
                ->first();

            if ($player instanceof Player) {
                $this->activate($request, $user, $player);
            } else {
                // Session input is never trusted as ownership evidence.
                $request->session()->forget($sessionKey);
            }
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
