<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Http\Middleware;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Http\Request;
use Inertia\Middleware;

final class HandleInertiaRequests extends Middleware
{
    protected $rootView = 'app';

    public function __construct(
        private readonly PlayerContext $playerContext,
        private readonly PlayerReferenceQuery $players,
    ) {}

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
        if (! $user instanceof AuthenticatedAccount) {
            return ['activePlayerId' => null, 'players' => []];
        }

        $players = $this->players->ownedByUser((int) $user->id);

        return [
            'activePlayerId' => $this->playerContext->playerOrNull()?->playerId,
            'players' => array_map(static fn (PlayerReference $player): array => [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'gamePlayerId' => $player->gamePlayerId,
                'kingdomNumber' => $player->kingdomNumber,
            ], $players),
        ];
    }
}
