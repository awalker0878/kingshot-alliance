<?php

declare(strict_types=1);

namespace App\ReadModels\RecruitmentManagement\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\Alliance\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentTextInput;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecruitmentCandidateDetailQuery
{
    public const HISTORY_PAGE_SIZE = 25;

    public function __construct(
        private AllianceAuthorization $authorization,
        private AllianceReferenceQuery $alliances,
        private PlayerReferenceQuery $players,
        private RecruitmentDuplicateFinder $duplicates,
        private TransferCampaignWorkspaceQuery $transferCampaign,
        private ScopedCursorCodec $cursors,
    ) {}

    /**
     * @param  array{notes?:string|null,history?:string|null,communications?:string|null,duplicates?:string|null,tags?:string|null,reviewers?:string|null}  $cursors
     * @return array<string,mixed>
     */
    public function forCandidate(string $actorPlayerId, string $allianceId, string $candidateId, array $cursors = []): array
    {
        $this->authorization->authorize($actorPlayerId, $allianceId, AlliancePermission::RecruitmentManage);
        $alliance = $this->alliances->require($allianceId);
        $record = RecruitmentCandidate::query()->where('alliance_id', $allianceId)->whereNull('anonymized_at')->whereKey($candidateId)->firstOrFail();
        $record->load([
            'answers',
            'onboarding.item',
        ]);

        $notes = $this->historyPage(RecruitmentNote::query()->where('alliance_id', $allianceId)->where('candidate_id', $candidateId), "recruitment-notes|{$allianceId}|{$candidateId}", 'created_at', $cursors['notes'] ?? null);
        $historyPage = $this->historyPage(RecruitmentStageHistory::query()->where('alliance_id', $allianceId)->where('candidate_id', $candidateId), "recruitment-history|{$allianceId}|{$candidateId}", 'changed_at', $cursors['history'] ?? null);
        $communications = $this->historyPage(RecruitmentCommunication::query()->where('alliance_id', $allianceId)->where('candidate_id', $candidateId), "recruitment-communications|{$allianceId}|{$candidateId}", 'created_at', $cursors['communications'] ?? null);
        $duplicates = $this->duplicates->forCandidate($allianceId, $record, $cursors['duplicates'] ?? null);

        $tags = $this->namedPage(DB::table('recruitment_tags as tag')
            ->join('recruitment_candidate_tags as attachment', 'attachment.tag_id', '=', 'tag.id')
            ->where('tag.alliance_id', $allianceId)->where('attachment.alliance_id', $allianceId)->where('attachment.candidate_id', $candidateId)
            ->selectRaw('tag.id AS id, tag.name AS name'), 'recruitment-tags|'.$allianceId.'|'.$candidateId, $cursors['tags'] ?? null);
        $reviewers = $this->namedPage(DB::table('recruitment_candidate_reviewers as attachment')
            ->join('players as player', 'player.id', '=', 'attachment.reviewer_player_id')
            ->where('attachment.alliance_id', $allianceId)->where('attachment.candidate_id', $candidateId)
            ->selectRaw('player.id AS id, player.current_name AS name'), 'recruitment-reviewers|'.$allianceId.'|'.$candidateId, $cursors['reviewers'] ?? null);

        $answerData = [];
        foreach ($record->answers as $answer) {
            $answerData[] = [
                'id' => (string) $answer->id,
                'prompt' => (string) $answer->prompt_snapshot,
                'type' => $answer->questionType()->value,
                'answer' => $answer->answer,
            ];
        }

        $playerReferences = $this->players->byIds(array_values(array_unique(array_map(
            static fn (RecruitmentNote $note): string => (string) $note->author_player_id, $notes->items,
        ))));

        $noteData = [];
        foreach ($notes->items as $note) {
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

        $historyData = [];
        foreach ($historyPage->items as $history) {
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
        foreach ($communications->items as $communication) {
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
        foreach ($duplicates->items as $duplicate) {
            $duplicateData[] = [
                'id' => (string) $duplicate->id,
                'name' => (string) $duplicate->full_name,
                'email' => (string) $duplicate->email,
                'contactHandle' => $duplicate->contact_handle,
                'stage' => $duplicate->recruitmentStage()->value,
                'submittedAt' => $duplicate->submitted_at->toIso8601String(),
            ];
        }

        return [
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
            'inputLimits' => ['note' => RecruitmentTextInput::NOTE_MAX_LENGTH, 'reason' => RecruitmentTextInput::REASON_MAX_LENGTH],
            'answers' => $answerData,
            'reviewersPage' => $reviewers->toArray(),
            'notesPage' => (new PageSlice($noteData, $notes->nextCursor, $notes->pageSize, $notes->isFirstPage))->toArray(),
            'tagsPage' => $tags->toArray(),
            'historyPage' => (new PageSlice($historyData, $historyPage->nextCursor, $historyPage->pageSize, $historyPage->isFirstPage))->toArray(),
            'communicationsPage' => (new PageSlice($communicationData, $communications->nextCursor, $communications->pageSize, $communications->isFirstPage))->toArray(),
            'onboarding' => $onboardingData,
            'duplicatesPage' => (new PageSlice($duplicateData, $duplicates->nextCursor, $duplicates->pageSize, $duplicates->isFirstPage))->toArray(),
            'selectionBaseUrl' => '/alliance/recruitment/'.$candidateId.'/options',
            'stageOptions' => $this->manualStageOptions($record->recruitmentStage()),
            'onboardingStatusOptions' => array_map(
                static fn (RecruitmentOnboardingStatus $status): string => $status->value,
                RecruitmentOnboardingStatus::cases(),
            ),
            'transferCampaign' => $this->transferCampaign->forCandidate($actorPlayerId, $allianceId, $record),
        ];
    }

    /** @return PageSlice<array{id:string,name:string}> */
    private function namedPage(QueryBuilder $base, string $scope, ?string $cursor): PageSlice
    {
        $query = DB::query()->fromSub($base, 'candidate_attachments');
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $name = $position['name'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($name) || mb_strlen($name) > 255 || ! is_string($id) || ! preg_match('/^[0-9A-HJKMNP-TV-Z]{26}$/Di', $id)) {
                throw ValidationException::withMessages(['cursor' => 'The candidate attachment cursor is invalid.']);
            }
            $query->where(static function (QueryBuilder $after) use ($name, $id): void {
                $after->where('name', '>', $name)->orWhere(static function (QueryBuilder $tie) use ($name, $id): void {
                    $tie->where('name', $name)->where('id', '>', $id);
                });
            });
        }
        $rows = $query->orderBy('name')->orderBy('id')->limit(26)->get();
        $items = array_values($rows->take(25)->map(static fn ($row): array => ['id' => (string) $row->id, 'name' => (string) $row->name])->all());
        $last = $items === [] ? null : $items[array_key_last($items)];

        return new PageSlice($items, $rows->count() > 25 && $last !== null ? $this->cursors->encode($scope, $last) : null, 25, $cursor === null);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return PageSlice<T>
     */
    private function historyPage(Builder $query, string $scope, string $column, ?string $cursor): PageSlice
    {
        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $scope);
            $at = $position['at'] ?? null;
            $id = $position['id'] ?? null;
            if (! is_string($at) || ! is_string($id)) {
                throw ValidationException::withMessages(['cursor' => 'The recruitment history cursor is incomplete.']);
            }
            $query->where(static function (Builder $row) use ($column, $at, $id): void {
                $row->where($column, '<', $at)->orWhere(static function (Builder $tie) use ($column, $at, $id): void {
                    $tie->where($column, $at)->where('id', '<', $id);
                });
            });
        }
        /** @var list<T> $rows */
        $rows = array_values($query->orderByDesc($column)->orderByDesc('id')->limit(self::HISTORY_PAGE_SIZE + 1)->get()->all());
        $items = array_slice($rows, 0, self::HISTORY_PAGE_SIZE);
        $last = $items === [] ? null : $items[array_key_last($items)];
        $nextCursor = count($rows) > self::HISTORY_PAGE_SIZE && $last instanceof Model
            ? $this->cursors->encode($scope, ['at' => (string) $last->getRawOriginal($column), 'id' => (string) $last->getKey()])
            : null;

        return new PageSlice($items, $nextCursor, self::HISTORY_PAGE_SIZE, $cursor === null || $cursor === '');
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
