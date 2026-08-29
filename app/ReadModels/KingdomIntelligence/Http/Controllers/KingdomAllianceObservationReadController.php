<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomIntelligence\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomAllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Observations\Http\Controllers\KingdomAllianceObservationController;
use App\Contexts\Intelligence\Observations\Queries\KingdomAllianceObservationQuery;
use App\ReadModels\KingdomIntelligence\Queries\KingdomIntelligenceTimelineQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/** Read-side composition that decorates Observation-owned history facts. */
final class KingdomAllianceObservationReadController extends Controller
{
    public function __invoke(
        Request $request,
        KingdomAllianceObservationController $historyPage,
        AllianceContext $context,
        AllianceIntelligenceAuthorization $authorization,
        KingdomAllianceObservationQuery $observations,
        AllianceReferenceQuery $alliances,
        KingdomReferenceQuery $kingdoms,
        KingdomAllianceReferenceQuery $kingdomAlliances,
        PlayerReferenceQuery $players,
        AccountIdentityQuery $accounts,
        KingdomIntelligenceTimelineQuery $timeline,
        string $tracking,
    ): Response {
        $response = $historyPage->show(
            $request,
            $context,
            $authorization,
            $observations,
            $alliances,
            $kingdoms,
            $kingdomAlliances,
            $players,
            $accounts,
            $tracking,
        );
        $scope = $context->scope();

        return $response->with('timeline', $timeline->forTrackedAlliance(
            $scope->playerId,
            $scope->allianceId,
            $tracking,
        ));
    }
}
