<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Http\Controllers;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Recruitment\Actions\AddRecruitmentNote;
use App\Domain\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Domain\Recruitment\Actions\ChangeRecruitmentStage;
use App\Domain\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Domain\Recruitment\Actions\MarkRecruitmentCommunicationSent;
use App\Domain\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Domain\Recruitment\Actions\PrepareRecruitmentDecisionCommunication;
use App\Domain\Recruitment\Actions\TagRecruitmentCandidate;
use App\Domain\Recruitment\Actions\UpdateRecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Domain\Recruitment\Models\RecruitmentCommunication;
use App\Domain\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
use App\Domain\Recruitment\Models\RecruitmentTag;
use App\Domain\Recruitment\Queries\RecruitmentDuplicateFinder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

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
        $this->authorize($authorization, $context->player(), $alliance);
        $record = $this->candidate($alliance, $candidate);
        $record->load([
            'answers',
            'player:id,current_name',
            'reviewers:id,current_name',
            'notes.author:id,current_name',
            'tags',
            'stageHistory',
            'communications',
            'onboarding.item',
        ]);

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('player:id,current_name')
            ->orderBy('created_at')
            ->get();
        $activeMemberPlayerIds = $memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all();
        $conversionPlayers = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('state', RosterState::Active->value)
            ->when($activeMemberPlayerIds !== [], static fn ($query) => $query->whereNotIn('player_id', $activeMemberPlayerIds))
            ->with('player:id,current_name,user_id,current_kingdom_id')
            ->orderBy('observed_name')
            ->get();

        $templates = RecruitmentDecisionTemplate::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->where('decision_stage', $record->recruitmentStage()->value)
            ->orderBy('name')
            ->get();

        $answerData = [];
        foreach ($record->answers as $answer) {
            $answerData[] = [
                'id' => (string) $answer->id,
                'prompt' => (string) $answer->prompt_snapshot,
                'type' => $answer->questionType()->value,
                'answer' => $answer->answer,
            ];
        }

        $reviewerData = [];
        foreach ($record->reviewers as $reviewer) {
            if (! $reviewer instanceof Player) {
                continue;
            }

            $reviewerData[] = [
                'id' => (string) $reviewer->id,
                'name' => (string) $reviewer->current_name,
            ];
        }

        $noteData = [];
        foreach ($record->notes->sortByDesc('created_at') as $note) {
            $author = $note->author;
            $noteData[] = [
                'id' => (string) $note->id,
                'body' => (string) $note->body,
                'author' => $author instanceof Player ? (string) $author->current_name : '—',
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
                'from' => $history->fromStage()?->value,
                'to' => $history->toStage()->value,
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
                'status' => $communication->communicationStatus()->value,
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
                'status' => $onboarding->onboardingStatus()->value,
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
                'stage' => $duplicate->recruitmentStage()->value,
                'submittedAt' => $duplicate->submitted_at->toIso8601String(),
            ];
        }

        $memberData = [];
        foreach ($memberships as $membership) {
            $player = $membership->player;
            if (! $player instanceof Player) {
                continue;
            }

            $memberData[] = [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'rank' => $membership->rank->value,
            ];
        }

        $conversionPlayerData = [];
        foreach ($conversionPlayers as $entry) {
            $player = $entry->player;
            if (! $player instanceof Player) {
                continue;
            }

            $conversionPlayerData[] = [
                'id' => (string) $player->id,
                'name' => (string) $player->current_name,
                'claimed' => $player->user_id !== null,
            ];
        }

        $templateData = [];
        foreach ($templates as $template) {
            $templateData[] = [
                'id' => (string) $template->id,
                'name' => (string) $template->name,
                'decisionStage' => $template->decisionStage()->value,
                'subject' => (string) $template->subject,
            ];
        }

        return Inertia::render('Alliance/Recruitment/Candidate', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
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
                'stage' => $record->recruitmentStage()->value,
                'submittedAt' => $record->submitted_at->toIso8601String(),
                'firstRespondedAt' => $record->first_responded_at?->toIso8601String(),
                'nextActionAt' => $record->next_action_at?->toIso8601String(),
                'acceptedAt' => $record->accepted_at?->toIso8601String(),
                'declinedAt' => $record->declined_at?->toIso8601String(),
                'withdrawnAt' => $record->withdrawn_at?->toIso8601String(),
                'joinedAt' => $record->joined_at?->toIso8601String(),
                'retentionDueAt' => $record->retention_due_at?->toIso8601String(),
                'playerId' => $record->player_id,
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
            'conversionPlayers' => $conversionPlayerData,
            'decisionTemplates' => $templateData,
            'stageOptions' => $this->manualStageOptions($record->recruitmentStage()),
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
            $context->player(),
            $alliance,
            $record,
            RecruitmentStage::from($validated['stage']),
            $validated['reason'] ?? null,
            isset($validated['next_action_at']) && is_string($validated['next_action_at']) ? CarbonImmutable::parse($validated['next_action_at'], (string) $alliance->timezone) : null,
        );

        return back();
    }

    public function assignReviewer(
        Request $request,
        AllianceContext $context,
        AssignRecruitmentReviewer $assign,
        string $candidate,
        string $player,
    ): RedirectResponse {
        $alliance = $context->alliance();
        $record = $this->candidate($alliance, $candidate);
        $reviewer = Player::query()->whereKey($player)->firstOrFail();

        $assign->handle($context->player(), $alliance, $record, $reviewer);

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
            $context->player(),
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
            $context->player(),
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
            $context->player(),
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

        $prepare->handle($context->player(), $alliance, $record, $decisionTemplate);

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
        $mark->handle($context->player(), $alliance, $record);

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
            $context->player(),
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
            $context->player(),
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

    private function authorize(AllianceAuthorization $authorization, Player $actor, Alliance $alliance): void
    {
        if (! $authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
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
