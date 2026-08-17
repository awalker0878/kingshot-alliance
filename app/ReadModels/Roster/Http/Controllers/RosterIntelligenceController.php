<?php

declare(strict_types=1);

namespace App\ReadModels\Roster\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\ReadModels\Roster\Services\RosterIntelligence;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class RosterIntelligenceController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        AccountIdentityQuery $accounts,
        RosterIntelligence $intelligence,
    ): Response {
        $scope = $context->scope();
        if (! $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::View)) {
            throw new AuthorizationException;
        }

        $account = $accounts->require((int) $request->user()?->getAuthIdentifier());
        $alliance = $alliances->require($scope->allianceId);
        $kingdom = $kingdoms->require($alliance->kingdomId);
        $canManage = $authorization->allows($scope->playerId, $scope->allianceId, IntelligencePermission::KingdomManage);
        $metrics = $intelligence->forAlliance($alliance->allianceId);

        return Inertia::render('Intelligence/Roster/Dossiers', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'kingdom' => (string) $kingdom->number,
            ],
            'canManage' => $canManage,
            'metrics' => [...$metrics, 'comparisons' => $canManage ? $metrics['comparisons'] : []],
        ]);
    }
}
