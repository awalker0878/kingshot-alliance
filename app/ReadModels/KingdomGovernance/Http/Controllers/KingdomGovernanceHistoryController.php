<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\ReadModels\KingdomGovernance\Queries\KingdomGovernanceTimelineQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class KingdomGovernanceHistoryController extends Controller
{
    public function __invoke(Request $request, AllianceContext $context, AllianceReferenceQuery $alliances, KingdomReferenceQuery $kingdoms, KingdomAuthorization $authorization, KingdomGovernanceTimelineQuery $timeline): Response
    {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->kingdomId, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($scope->kingdomId);
        $history = $timeline->forKingdom($scope->kingdomId, $request->query('before'));
        return Inertia::render('Kingdom/Governance/History', ['user' => ['name' => $user->accountName(), 'email' => $user->accountEmail()], 'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name], 'kingdom' => ['id' => $kingdom->kingdomId, 'number' => $kingdom->number], 'items' => $history['items'], 'nextCursor' => $history['nextCursor']]);
    }
}
