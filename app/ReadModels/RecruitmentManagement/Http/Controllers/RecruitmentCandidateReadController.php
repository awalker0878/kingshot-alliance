<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Http\Controllers;

use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Recruitment\Http\Controllers\RecruitmentCandidateController;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\RecruitmentManagement\Queries\TransferCampaignWorkspaceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\Request;
use Inertia\Response;

/** Read-side composition that decorates Recruitment-owned candidate facts. */
final class RecruitmentCandidateReadController extends Controller
{
    public function __invoke(
        Request $request,
        RecruitmentCandidateController $candidatePage,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RecruitmentDuplicateFinder $duplicates,
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
        TransferCampaignWorkspaceQuery $transferCampaign,
        string $candidate,
    ): Response {
        $response = $candidatePage->show(
            $request,
            $context,
            $authorization,
            $duplicates,
            $alliances,
            $players,
            $candidate,
        );
        $scope = $context->scope();
        $record = RecruitmentCandidate::query()
            ->where('alliance_id', $scope->allianceId)
            ->whereKey($candidate)
            ->firstOrFail();

        return $response->with('transferCampaign', $transferCampaign->forCandidate(
            $scope->playerId,
            $scope->allianceId,
            $record,
        ));
    }
}
