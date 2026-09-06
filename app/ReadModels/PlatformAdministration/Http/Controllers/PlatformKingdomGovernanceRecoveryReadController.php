<?php

declare(strict_types=1);

namespace App\ReadModels\PlatformAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class PlatformKingdomGovernanceRecoveryReadController extends Controller
{
    public function __invoke(Request $request): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $kingdoms = Kingdom::query()->orderBy('number')->limit(250)->get(['id', 'number']);
        $kingdomIds = $kingdoms->pluck('id')->map('strval')->all();
        $players = Player::query()->whereIn('current_kingdom_id', $kingdomIds)->orderBy('current_name')->limit(1000)->get(['id', 'current_kingdom_id', 'current_name', 'game_player_id']);
        return Inertia::render('Platform/GovernanceRecovery', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'kingdoms' => $kingdoms->map(static fn (Kingdom $kingdom): array => ['id' => (string) $kingdom->id, 'number' => (int) $kingdom->number])->values()->all(),
            'players' => $players->map(static fn (Player $player): array => ['id' => (string) $player->id, 'kingdomId' => (string) $player->current_kingdom_id, 'name' => (string) $player->current_name, 'gamePlayerId' => $player->game_player_id])->values()->all(),
        ]);
    }
}
