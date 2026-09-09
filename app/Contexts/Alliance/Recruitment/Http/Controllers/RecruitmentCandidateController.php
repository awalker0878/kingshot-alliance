<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Recruitment\Actions\AddRecruitmentNote;
use App\Contexts\Alliance\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Contexts\Alliance\Recruitment\Actions\ChangeRecruitmentStage;
use App\Contexts\Alliance\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\MarkRecruitmentCommunicationSent;
use App\Contexts\Alliance\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Contexts\Alliance\Recruitment\Actions\PrepareRecruitmentDecisionCommunication;
use App\Contexts\Alliance\Recruitment\Actions\TagRecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Actions\UpdateRecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentTextInput;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

final class RecruitmentCandidateController extends Controller
{
    public function updateStage(
        Request $request,
        AllianceContext $context,
        ChangeRecruitmentStage $change,
        AllianceReferenceQuery $alliances,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(RecruitmentStage::class), Rule::notIn([RecruitmentStage::Joined->value])],
            'reason' => ['nullable', 'string', 'max:'.RecruitmentTextInput::REASON_MAX_LENGTH],
            'next_action_at' => ['nullable', 'date'],
        ]);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);

        $change->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidate,
            RecruitmentStage::from($validated['stage']),
            $validated['reason'] ?? null,
            isset($validated['next_action_at']) && is_string($validated['next_action_at']) ? CarbonImmutable::parse($validated['next_action_at'], $alliance->timezone) : null,
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-candidate-stage-updated'));
    }

    public function assignReviewer(
        Request $request,
        AllianceContext $context,
        AssignRecruitmentReviewer $assign,
        string $candidate,
        string $player,
    ): RedirectResponse {
        $scope = $context->scope();
        $assign->handle($scope->playerId, $scope->allianceId, $candidate, $player);

        return back()->with('actionReceipt', $this->receipt('recruitment-reviewer-assigned'));
    }

    public function addNote(
        Request $request,
        AllianceContext $context,
        AddRecruitmentNote $add,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate(['body' => ['required', 'string', 'max:'.RecruitmentTextInput::NOTE_MAX_LENGTH]]);
        $scope = $context->scope();
        $add->handle($scope->playerId, $scope->allianceId, $candidate, $validated['body']);

        return back()->with('actionReceipt', $this->receipt('recruitment-note-added'));
    }

    public function tag(
        Request $request,
        AllianceContext $context,
        TagRecruitmentCandidate $tag,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $scope = $context->scope();
        $tag->handle($scope->playerId, $scope->allianceId, $candidate, $validated['name']);

        return back()->with('actionReceipt', $this->receipt('recruitment-tag-added'));
    }

    public function merge(
        Request $request,
        AllianceContext $context,
        MergeRecruitmentCandidates $merge,
        string $candidate,
        string $target,
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:'.RecruitmentTextInput::REASON_MAX_LENGTH]]);
        $scope = $context->scope();
        $mergedCandidateId = $merge->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidate,
            $target,
            $validated['reason'] ?? null,
        );

        return redirect()
            ->route('alliance.recruitment.candidates.show', $mergedCandidateId)
            ->with('actionReceipt', $this->receipt('recruitment-candidates-merged'));
    }

    public function prepareCommunication(
        Request $request,
        AllianceContext $context,
        PrepareRecruitmentDecisionCommunication $prepare,
        string $candidate,
        string $template,
    ): RedirectResponse {
        $scope = $context->scope();
        $prepare->handle($scope->playerId, $scope->allianceId, $candidate, $template);

        return back()->with('actionReceipt', $this->receipt('recruitment-communication-prepared'));
    }

    public function markCommunicationSent(
        Request $request,
        AllianceContext $context,
        MarkRecruitmentCommunicationSent $mark,
        string $communication,
    ): RedirectResponse {
        $scope = $context->scope();
        $mark->handle($scope->playerId, $scope->allianceId, $communication);

        return back()->with('actionReceipt', $this->receipt('recruitment-communication-marked-sent'));
    }

    public function convert(
        Request $request,
        AllianceContext $context,
        ConvertAcceptedRecruitmentCandidate $convert,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'ulid'],
        ]);
        $scope = $context->scope();
        $converted = $convert->handle(
            $scope->playerId,
            $scope->allianceId,
            $candidate,
            (string) $validated['player_id'],
        );

        if ($converted->token !== null) {
            $request->session()->flash(
                'recruitmentMembershipInvitationLink',
                route('invitations.show', $converted->token),
            );
        }

        return back()->with('actionReceipt', $this->receipt('recruitment-membership-invite-prepared'));
    }

    public function updateOnboarding(
        Request $request,
        AllianceContext $context,
        UpdateRecruitmentOnboardingStatus $update,
        string $onboarding,
    ): RedirectResponse {
        $validated = $request->validate([
            'status' => ['required', Rule::enum(RecruitmentOnboardingStatus::class)],
        ]);
        $scope = $context->scope();
        $update->handle(
            $scope->playerId,
            $scope->allianceId,
            $onboarding,
            RecruitmentOnboardingStatus::from($validated['status']),
        );

        return back()->with('actionReceipt', $this->receipt('recruitment-onboarding-updated'));
    }
}
