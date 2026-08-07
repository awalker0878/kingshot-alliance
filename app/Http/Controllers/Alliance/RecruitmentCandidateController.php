<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alliance;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AllianceContext;
use App\Application\Recruitment\AddRecruitmentNote;
use App\Application\Recruitment\AssignRecruitmentReviewer;
use App\Application\Recruitment\ChangeRecruitmentStage;
use App\Application\Recruitment\ConvertAcceptedRecruitmentCandidate;
use App\Application\Recruitment\MarkRecruitmentCommunicationSent;
use App\Application\Recruitment\MergeRecruitmentCandidates;
use App\Application\Recruitment\PrepareRecruitmentDecisionCommunication;
use App\Application\Recruitment\RecruitmentDuplicateFinder;
use App\Application\Recruitment\TagRecruitmentCandidate;
use App\Application\Recruitment\UpdateRecruitmentOnboardingStatus;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Http\Controllers\Controller;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\RecruitmentCandidate;
use App\Models\RecruitmentCandidateOnboarding;
use App\Models\RecruitmentCommunication;
use App\Models\RecruitmentDecisionTemplate;
use App\Models\RecruitmentStageHistory;
use App\Models\RecruitmentTag;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class RecruitmentCandidateController extends Controller
{
    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        RecruitmentDuplicateFinder $duplicates,
        string $candidate,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance();
        $this->authorize($authorization, $user, $alliance);
        $record = $this->candidate($alliance, $candidate);
        $record->load([
            'answers',
            'reviewers.user:id,name',
            'notes.authorMembership.user:id,name',
            'tags',
            'stageHistory',
            'communications',
            'onboarding.item',
            'membershipInvitation',
        ]);

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('user:id,name')
            ->orderBy('created_at')
            ->get();
        $templates = RecruitmentDecisionTemplate::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->where('decision_stage', $record->stage->value)
            ->orderBy('name')
            ->get();

        $answerData = [];
        foreach ($record->answers as $answer) {
            $answerData[] = [
                'id' => (string) $answer->id,
                'prompt' => (string) $answer->prompt_snapshot,
                'type' => $answer->question_type_snapshot->value,
                'answer' => $answer->answer,
            ];
        }

        $reviewerData = [];
        foreach ($record->reviewers as $membership) {
            $member = $membership->user;
            if (! $member instanceof User) {
                throw new LogicException('A recruitment reviewer membership must reference a user.');
            }

            $reviewerData[] = [
                'id' => (string) $membership->id,
                'name' => (string) $member->name,
            ];
        }

        $noteData = [];
        foreach ($record->notes->sortByDesc('created_at') as $note) {
            $authorMembership = $note->authorMembership;
            $author = $authorMembership instanceof AllianceMembership ? $authorMembership->user : null;
            $noteData[] = [
                'id' => (string) $note->id,
                'body' => (string) $note->body,
                'author' => $author instanceof User ? (string) $author->name : 'Former member',
                'createdAt' => $note->created_at?->toIso8601String(),
            ];
        }

        $tagData = [];
        foreach ($record->tags->sortBy('name') as $tag) {
            if ($tag instanceof RecruitmentTag) {
                $tagData[] = [
                    'id' => (string) $tag->id,
                    'name' => (string) $tag->name,
                ];
            }
        }

        $historyData = [];
        foreach ($record->stageHistory->sortByDesc('changed_at') as $history) {
            if (! $history instanceof RecruitmentStageHistory) {
                continue;
            }

            $historyData[] = [
                'id' => (string) $history->id,
                'from' => $history->from_stage?->value,
                'to' => $history->to_stage->value,
                'reason' => $history->reason,
                'changedAt' => $history->changed_at->toIso8601String(),
            ];
        }

        $communicationData = [];
        foreach ($record->communications->sortByDesc('created_at') as $communication) {
            if (! $communication instanceof RecruitmentCommunication) {
                continue;
            }

            $communicationData[] = [
                'id' => (string) $communication->id,
                'subject' => (string) $communication->subject,
                'body' => (string) $communication->body,
                'status' => $communication->status->value,
                'sentAt' => $communication->sent_at?->toIso8601String(),
                'createdAt' => $communication->created_at?->toIso8601String(),
            ];
        }

        $onboardingData = [];
        foreach ($record->onboarding->sortBy('item.position') as $onboarding) {
            if (! $onboarding instanceof RecruitmentCandidateOnboarding) {
                continue;
            }
            $item = $onboarding->item;
            if ($item === null) {
                continue;
            }

            $onboardingData[] = [
                'id' => (string) $onboarding->id,
                'name' => (string) $item->name,
                'description' => $item->description,
                'required' => (bool) $item->is_required,
                'status' => $onboarding->status->value,
                'completedAt' => $onboarding->completed_at?->toIso8601String(),
            ];
        }

        $duplicateData = [];
        foreach ($duplicates->forCandidate($alliance, $record) as $duplicate) {
            $duplicateData[] = [
                'id' => (string) $duplicate->id,
                'name' => (string) $duplicate->full_name,
                'email' => (string) $duplicate->email,
                'contactHandle' => $duplicate->contact_handle,
                'stage' => $duplicate->stage->value,
                'submittedAt' => $duplicate->submitted_at->toIso8601String(),
            ];
        }

        $memberData = [];
        foreach ($memberships as $membership) {
            $member = $membership->user;
            if (! $member instanceof User) {
                throw new LogicException('An active alliance membership must reference a user.');
            }

            $memberData[] = [
                'id' => (string) $membership->id,
                'name' => (string) $member->name,
            ];
        }

        $templateData = [];
        foreach ($templates as $template) {
            $templateData[] = [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'decisionStage' => $template->decision_stage->value,
                'subject' => (string) $template->subject,
            ];
        }

        return Inertia::render('Alliance/Recruitment/Candidate', [
            'alliance' => [
                'id' => (string) $alliance->id,
                'name' => (string) $alliance->name,
            ],
            'candidate' => [
                'id' => (string) $record->id,
                'name' => (string) $record->full_name,
                'email' => (string) $record->email,
                'contactHandle' => $record->contact_handle,
                'source' => $record->source,
                'stage' => $record->stage->value,
                'submittedAt' => $record->submitted_at->toIso8601String(),
                'firstRespondedAt' => $record->first_responded_at?->toIso8601String(),
                'nextActionAt' => $record->next_action_at?->toIso8601String(),
                'acceptedAt' => $record->accepted_at?->toIso8601String(),
                'declinedAt' => $record->declined_at?->toIso8601String(),
                'withdrawnAt' => $record->withdrawn_at?->toIso8601String(),
                'joinedAt' => $record->joined_at?->toIso8601String(),
                'retentionDueAt' => $record->retention_due_at?->toIso8601String(),
                'membershipInvitationId' => $record->membership_invitation_id,
            ],
            'answers' => $answerData,
            'reviewers' => $reviewerData,
            'notes' => $noteData,
            'tags' => $tagData,
            'history' => $historyData,
            'communications' => $communicationData,
            'onboarding' => $onboardingData,
            'duplicates' => $duplicateData,
            'members' => $memberData,
            'decisionTemplates' => $templateData,
            'stageOptions' => $this->manualStageOptions($record->stage),
            'onboardingStatusOptions' => array_map(
                static fn (RecruitmentOnboardingStatus $status): string => $status->value,
                RecruitmentOnboardingStatus::cases(),
            ),
            'issuedMembershipInvitationLink' => $request->session()->pull('recruitmentMembershipInvitationLink'),
        ]);
    }

    public function updateStage(
        Request $request,
        AllianceContext $context,
        ChangeRecruitmentStage $change,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(RecruitmentStage::class), Rule::notIn([RecruitmentStage::Joined->value])],
            'reason' => ['nullable', 'string', 'max:5000'],
            'next_action_at' => ['nullable', 'date'],
        ]);
        $alliance = $context->alliance();
        $record = $this->candidate($alliance, $candidate);

        $change->handle(
            $this->user($request),
            $alliance,
            $record,
            RecruitmentStage::from($validated['stage']),
            $validated['reason'] ?? null,
            isset($validated['next_action_at']) ? CarbonImmutable::parse($validated['next_action_at']) : null,
        );

        return back();
    }

    public function assignReviewer(
        Request $request,
        AllianceContext $context,
        AssignRecruitmentReviewer $assign,
        string $candidate,
        string $membership,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $record = $this->candidate($alliance, $candidate);
        $reviewer = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->whereKey($membership)
            ->firstOrFail();

        $assign->handle($this->user($request), $alliance, $record, $reviewer);

        return back();
    }

    public function addNote(
        Request $request,
        AllianceContext $context,
        AddRecruitmentNote $add,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);
        $alliance = $context->alliance();
        $add->handle(
            $this->user($request),
            $alliance,
            $this->candidate($alliance, $candidate),
            $validated['body'],
        );

        return back();
    }

    public function tag(
        Request $request,
        AllianceContext $context,
        TagRecruitmentCandidate $tag,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate(['name' => ['required', 'string', 'max:80']]);
        $alliance = $context->alliance();
        $tag->handle(
            $this->user($request),
            $alliance,
            $this->candidate($alliance, $candidate),
            $validated['name'],
        );

        return back();
    }

    public function merge(
        Request $request,
        AllianceContext $context,
        MergeRecruitmentCandidates $merge,
        string $candidate,
        string $target,
    ): RedirectResponse {
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);
        $alliance = $context->alliance();
        $sourceRecord = $this->candidate($alliance, $candidate);
        $targetRecord = $this->candidate($alliance, $target);
        $merged = $merge->handle(
            $this->user($request),
            $alliance,
            $sourceRecord,
            $targetRecord,
            $validated['reason'] ?? null,
        );

        return redirect()->route('alliance.recruitment.candidates.show', $merged->id);
    }

    public function prepareCommunication(
        Request $request,
        AllianceContext $context,
        PrepareRecruitmentDecisionCommunication $prepare,
        string $candidate,
        string $template,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $record = $this->candidate($alliance, $candidate);
        $decisionTemplate = RecruitmentDecisionTemplate::query()
            ->where('alliance_id', $alliance->id)
            ->whereKey($template)
            ->firstOrFail();

        $prepare->handle($this->user($request), $alliance, $record, $decisionTemplate);

        return back();
    }

    public function markCommunicationSent(
        Request $request,
        AllianceContext $context,
        MarkRecruitmentCommunicationSent $mark,
        string $communication,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $record = RecruitmentCommunication::query()
            ->where('alliance_id', $alliance->id)
            ->whereKey($communication)
            ->firstOrFail();
        $mark->handle($this->user($request), $alliance, $record);

        return back();
    }

    public function convert(
        Request $request,
        AllianceContext $context,
        ConvertAcceptedRecruitmentCandidate $convert,
        string $candidate,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $converted = $convert->handle(
            $this->user($request),
            $alliance,
            $this->candidate($alliance, $candidate),
        );

        if ($converted->token !== null) {
            $request->session()->flash(
                'recruitmentMembershipInvitationLink',
                route('invitations.show', $converted->token),
            );
        }

        return back();
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
        $alliance = $context->alliance();
        $record = RecruitmentCandidateOnboarding::query()
            ->where('alliance_id', $alliance->id)
            ->whereKey($onboarding)
            ->firstOrFail();
        $update->handle(
            $this->user($request),
            $alliance,
            $record,
            RecruitmentOnboardingStatus::from($validated['status']),
        );

        return back();
    }

    private function candidate(Alliance $alliance, string $candidate): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()
            ->where('alliance_id', $alliance->id)
            ->whereKey($candidate)
            ->firstOrFail();
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }

    private function authorize(AllianceAuthorization $authorization, User $user, Alliance $alliance): void
    {
        if (! $authorization->allows($user, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException;
        }
    }

    /** @return list<string> */
    private function manualStageOptions(RecruitmentStage $stage): array
    {
        return array_values(array_map(
            static fn (RecruitmentStage $target): string => $target->value,
            array_filter(
                $stage->allowedTransitions(),
                static fn (RecruitmentStage $target): bool => $target !== RecruitmentStage::Joined,
            ),
        ));
    }
}
