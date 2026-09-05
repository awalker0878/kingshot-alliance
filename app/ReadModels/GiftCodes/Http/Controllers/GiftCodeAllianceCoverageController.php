<?php

declare(strict_types=1);

namespace App\ReadModels\GiftCodes\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\ReadModels\GiftCodes\Queries\GiftCodeAllianceCoverageQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class GiftCodeAllianceCoverageController extends Controller
{
    public function __invoke(
        Request $request,
        string $alliance,
        PlayerContext $context,
        AllianceAuthorization $authorization,
        GiftCodeAllianceCoverageQuery $coverage,
    ): Response {
        abort_unless((bool) config('game_world.gift_codes.alliance_coverage', false), 404);
        $account = $request->user();
        abort_unless($account instanceof AuthenticatedAccount, 401);
        $player = $context->playerOrNull();
        abort_unless($player instanceof PlayerReference, 409, 'Select a Governor before opening Alliance Gift Code coverage.');
        $authorization->authorize($player->playerId, $alliance, AlliancePermission::GiftCodeCoverage);

        return Inertia::render('Kingdom/GiftCodes/AllianceCoverage', [
            'user' => ['name' => $account->accountName(), 'email' => $account->accountEmail()],
            'player' => ['id' => $player->playerId, 'name' => $player->currentName],
            'allianceId' => $alliance,
            'coverage' => $coverage->forAlliance($alliance),
        ]);
    }
}
