<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Http\Controllers;

use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
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
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentDecisionTemplate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentTag;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\ReadModels\RecruitmentManagement\Queries\TransferCampaignWorkspaceQuery;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
        AllianceReferenceQuery $alliances,
        PlayerReferenceQuery $players,
        TransferCampaignWorkspaceQuery $transferCampaign,
        string $candidate,
    ): Response {
        $user = $this->user($request);
        $scope = $context->scope();
        $alliance = $alliances->require($scope->allianceId);
        $this->authorize($authorization, $scope->playerId, $scope->allianceId);
        $record = $this->candidate($scope->allianceId, $candidate);
        $record->load([
            'answers',
            'notes',
            'tags',
            'stageHistory',
            'communications',
            'onboarding.item',
        ]);

        $memberships = AllianceMembership::query()
            ->where('alliance_id', $scope->allianceId)
            ->where('status', MembershipStatus::Active->value)
            ->orderBy('created_at')
            ->get();
        $activeMemberPlayerIds = $memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all();
        $conversionPlayers = AllianceRosterEntry::query()
            ->where('alliance_id', $scope->allianceId)
            ->where('state', RosterState::Active->value)
            ->when($activeMemberPlayerIds !== [], static fn ($query) => $query->whereNotIn('player_id', $activeMemberPlayerIds))
            ->orderBy('observed_name')
            ->get();

        $templates = RecruitmentDecisionTemplate::query()
            ->where('alliance_id', $scope->allianceId)
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

        $reviewerIds = DB::table('recruitment_candidate_reviewers')
            ->where('candidate_id', $record->id)
            ->orderBy('reviewer_player_id')
            ->pluck('reviewer_player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
        $playerIds = array_values(array_unique(array_merge(
            $reviewerIds,
            $record->notes->pluck('author_player_id')->filter()->map(static fn ($id): string => (string) $id)->all(),
            $memberships->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
            $conversionPlayers->pluck('player_id')->map(static fn ($id): string => (string) $id)->all(),
        )));
        $playerReferences = $players->byIds($playerIds);

        $reviewerData = [];
        foreach ($reviewerIds as $reviewerPlayerId) {
            $reviewer = $playerReferences[$reviewerPlayerId] ?? null;
            if ($reviewer === null) {
                continue;
            }

            $reviewerData[] = [
                'id' => $reviewer->playerId,
                'name' => $reviewer->currentName,
            ];
        }

        $noteData = [];
        foreach ($record->notes->sortByDesc('created_at') as $note) {
            $author = $note->author_player_id === null
                ? null
                : ($playerReferences[(string) $note->author_player_id] ?? null);
            $noteData[] = [
                'id' => (string) $note->id,
                'body' => (string) $note->body,
                'author' => $author->currentName ?? '—',
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
        foreach ($duplicates->forCandidate($scope->allianceId, $record) as $duplicate) {
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
            $player = $playerReferences[(string) $membership->player_id] ?? null;
            if ($player === null) {
                continue;
            }

            $memberData[] = [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'rank' => $membership->rank->value,
            ];
        }

        $conversionPlayerData = [];
        foreach ($conversionPlayers as $entry) {
            $player = $playerReferences[(string) $entry->player_id] ?? null;
            if ($player === null) {
                continue;
            }

            $conversionPlayerData[] = [
                'id' => $player->playerId,
                'name' => $player->currentName,
                'claimed' => $player->claimed(),
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
                'name' => $user->accountName(),
                'email' => $user->accountEmail(),
            ],
            'alliance' => [
                'id' => $alliance->allianceId,
                'name' => $alliance->name,
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
            'transferCampaign' => $transferCampaign->forCandidate(
                $scope->playerId,
                $scope->allianceId,
                $record,
            ),
        ]);
    }

    public function updateStage(
        Request $request,
        AllianceContext $context,
        ChangeRecruitmentStage $change,
        AllianceReferenceQuery $alliances,
        string $candidate,
    ): RedirectResponse {
        $validated = $request->validate([
            'stage' => ['required', Rule::enum(RecruitmentStage::class), Rule::notIn([RecruitmentStage::Joined->value])],
            'reason' => ['nullable', 'string', 'max:5000'],
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
        $validated = $request->validate(['body' => ['required', 'string', 'max:10000']]);
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
        $validated = $request->validate(['reason' => ['nullable', 'string', 'max:5000']]);
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

    private function candidate(string $allianceId, string $candidate): RecruitmentCandidate
    {
        return RecruitmentCandidate::query()
            ->where('alliance_id', $allianceId)
            ->whereKey($candidate)
            ->firstOrFail();
    }

    private function user(Request $request): AuthenticatedAccount
    {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);

        return $user;
    }

    private function authorize(AllianceAuthorization $authorization, string $actorPlayerId, string $allianceId): void
    {
        if (! $authorization->allows($actorPlayerId, $allianceId, AlliancePermission::RecruitmentManage)) {
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
