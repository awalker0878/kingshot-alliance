<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentApplicationMode;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentQuestionType;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\ReadModels\RecruitmentManagement\Queries\RecruitmentManagementQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RecruitmentManagementReadController extends Controller
{
    public function __invoke(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        RecruitmentManagementQuery $management,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        if (! $authorization->allows($scope->playerId, $scope->allianceId, AlliancePermission::RecruitmentManage)) {
            throw new AuthorizationException;
        }

        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:160'],
            'stage' => ['nullable', Rule::enum(RecruitmentStage::class)],
            'source' => ['nullable', 'string', 'max:160'],
            'cursor' => ['nullable', 'string', 'max:4096'],
        ]);
        $projection = $management->forAlliance(
            $scope->allianceId,
            $filters,
            isset($filters['cursor']) ? (string) $filters['cursor'] : null,
        );
        $settings = $projection['settings'];

        return Inertia::render('Alliance/Recruitment/Index', [
            'user' => [
                'name' => $user->accountName(),
                'email' => $user->accountEmail(),
            ],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
                'slug' => $alliance->slug,
            ],
            'settings' => $settings,
            'applicationModes' => array_column(RecruitmentApplicationMode::cases(), 'value'),
            'questionTypes' => array_column(RecruitmentQuestionType::cases(), 'value'),
            'candidateStages' => array_column(RecruitmentStage::cases(), 'value'),
            'questions' => $projection['questions'],
            'candidatePage' => $projection['candidatePage'],
            'candidateFilters' => $projection['candidateFilters'],
            'members' => $projection['members'],
            'decisionTemplates' => $projection['decisionTemplates'],
            'onboardingItems' => $projection['onboardingItems'],
            'metrics' => $projection['metrics'],
            'discovery' => [
                'boardUrl' => route('public.recruitment.index'),
                'applicationUrl' => $settings !== null
                    && $settings['open']
                    && $settings['mode'] === RecruitmentApplicationMode::Public->value
                        ? route('public.alliances.recruitment.show', [
                            'slug' => $alliance->slug,
                            'source' => 'alliance-share',
                        ])
                        : null,
            ],
            'issuedApplicationLink' => $request->session()->pull('recruitmentApplicationLink'),
            'bulkPreview' => $request->session()->pull('recruitmentBulkPreview'),
            'bulkResult' => $request->session()->pull('recruitmentBulkResult'),
        ]);
    }
}
