<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Recruitment\Actions\SetRecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentReentryPolicy;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class RecruitmentReentryController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceReferenceQuery $alliances,
        RecruitmentReentryPolicy $policy,
        string $candidate,
    ): Response {
        $user = $request->user();
        abort_unless($user instanceof User, 401);
        $scope = $context->scope();
        $authorization->authorize($scope->playerId, $scope->allianceId, AlliancePermission::RecruitmentManage);
        $alliance = $alliances->require($scope->allianceId);
        $record = RecruitmentCandidate::query()
            ->whereKey($candidate)
            ->where('alliance_id', $scope->allianceId)
            ->whereNull('merged_into_id')
            ->firstOrFail();

        return Inertia::render('Alliance/Recruitment/Reentry', [
            'user' => ['name' => (string) $user->name, 'email' => (string) $user->email],
            'alliance' => ['id' => $alliance->allianceId, 'name' => $alliance->name],
            'candidate' => [
                'id' => (string) $record->id,
                'name' => (string) $record->full_name,
                'email' => (string) $record->email,
                'stage' => $record->recruitmentStage()->value,
                'control' => $record->reentry_control->value,
                'reason' => $record->reentry_reason,
                'reviewAt' => $record->reentry_review_at?->toIso8601String(),
                'setAt' => $record->reentry_set_at?->toIso8601String(),
                'blocking' => $policy->isBlocking($record),
            ],
            'controls' => array_map(
                static fn (RecruitmentReentryControl $control): string => $control->value,
                RecruitmentReentryControl::cases(),
            ),
        ]);
    }

    public function update(
        Request $request,
        AllianceContext $context,
        SetRecruitmentReentryControl $set,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'control' => ['required', Rule::enum(RecruitmentReentryControl::class)],
            'reason' => ['nullable', 'string', 'max:5000'],
            'review_at' => ['nullable', 'date'],
        ]);
        $scope = $context->scope();
        $set->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidate,
            RecruitmentReentryControl::from((string) $validated['control']),
            isset($validated['reason']) ? (string) $validated['reason'] : null,
            isset($validated['review_at']) ? (string) $validated['review_at'] : null,
        );

        return redirect()->route('alliance.recruitment.reentry.show', ['candidate' => $candidate])
            ->with('actionReceipt', $this->receipt('recruitment-reentry-updated'));
    }
}
