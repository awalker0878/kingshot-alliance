<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Http\Middleware;

use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(private readonly PlayerContext $playerContext) {}

    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /** @return array<string, mixed> */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'applicationName' => config('app.name'),
            'playerContext' => fn (): array => $this->playerContextPayload($request),
        ];
    }

    /** @return array{activePlayerId:?string,players:list<array{id:string,name:string,gamePlayerId:?string,kingdomNumber:?int}>} */
    private function playerContextPayload(Request $request): array
    {
        $user = $request->user();
        if (! $user instanceof User) {
            return ['activePlayerId' => null, 'players' => []];
        }

        $players = Player::query()
            ->where('user_id', $user->id)
            ->with('currentKingdom:id,number')
            ->orderBy('current_name')
            ->orderBy('id')
            ->get();

        return [
            'activePlayerId' => $this->playerContext->playerOrNull()?->id,
            'players' => array_values($players->map(static fn (Player $player): array => [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'gamePlayerId' => $player->game_player_id === null ? null : (string) $player->game_player_id,
                'kingdomNumber' => $player->currentKingdom?->number === null ? null : (int) $player->currentKingdom->number,
            ])->all()),
        ];
    }
}
