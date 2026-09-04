<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Contexts\Alliance\Recruitment\Services\RecruitmentReentryPolicy;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MergeRecruitmentCandidates
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private RecruitmentReentryPolicy $reentryPolicy,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $sourceCandidateId,
        string $targetCandidateId,
        ?string $reason = null,
    ): string {
        if ($sourceCandidateId === $targetCandidateId) {
            throw ValidationException::withMessages(['candidate' => 'A recruitment candidate cannot be merged into itself.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $sourceCandidateId, $targetCandidateId, $reason): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereIn('id', [$sourceCandidateId, $targetCandidateId])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy(fn (RecruitmentCandidate $candidate): string => (string) $candidate->id);

            $sourceCandidate = $locked->get($sourceCandidateId);
            $targetCandidate = $locked->get($targetCandidateId);
            if (! $sourceCandidate instanceof RecruitmentCandidate || ! $targetCandidate instanceof RecruitmentCandidate) {
                throw ValidationException::withMessages(['candidate' => 'Both recruitment candidates must belong to the active Alliance.']);
            }
            if ((string) $sourceCandidate->merged_into_id === (string) $targetCandidate->id) {
                return (string) $targetCandidate->id;
            }
            if ($sourceCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['source' => 'The source candidate has already been merged.']);
            }
            if ($targetCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['target' => 'A candidate that was merged into another record cannot be the merge target.']);
            }

            $this->copyReviewers((string) $context->alliance->id, $sourceCandidate, $targetCandidate, $context->actor->playerId);
            $this->copyTags((string) $context->alliance->id, $sourceCandidate, $targetCandidate);
            $reentry = $this->reentryPolicy->stricter($sourceCandidate, $targetCandidate);

            $targetCandidate->forceFill([
                'contact_handle' => $targetCandidate->contact_handle ?: $sourceCandidate->contact_handle,
                'source' => $targetCandidate->source ?: $sourceCandidate->source,
                'next_action_at' => $targetCandidate->next_action_at ?: $sourceCandidate->next_action_at,
                'reentry_control' => $reentry['control'],
                'reentry_reason' => $reentry['reason'],
                'reentry_review_at' => $reentry['reviewAt'],
                'reentry_set_by_player_id' => $reentry['setBy'],
                'reentry_set_at' => $reentry['setAt'],
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            if ($reason !== null && trim($reason) !== '') {
                RecruitmentNote::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'candidate_id' => $targetCandidate->id,
                    'author_player_id' => $context->actor->playerId,
                    'body' => 'Merge reason: '.trim($reason),
                ]);
            }

            $sourceCandidate->forceFill([
                'merged_into_id' => $targetCandidate->id,
                'next_action_at' => null,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $metadata = [
                'source_candidate_id' => (string) $sourceCandidate->id,
                'target_candidate_id' => (string) $targetCandidate->id,
                'reentry_control' => $reentry['control']->value,
            ];
            $this->audit->record('recruitment.candidate.merged', $context->actor, $sourceCandidate, $context->alliance, $metadata);
            $this->outbox->record('recruitment.candidate.merged', (string) $context->alliance->id, $sourceCandidate, $metadata);

            return (string) $targetCandidate->id;
        });
    }

    private function copyReviewers(string $allianceId, RecruitmentCandidate $source, RecruitmentCandidate $target, string $actorPlayerId): void
    {
        $reviewerIds = DB::table('recruitment_candidate_reviewers')->where('candidate_id', $source->id)->orderBy('reviewer_player_id')->pluck('reviewer_player_id');
        foreach ($reviewerIds as $reviewerPlayerId) {
            DB::table('recruitment_candidate_reviewers')->insertOrIgnore([
                'id' => (string) Str::ulid(), 'alliance_id' => $allianceId, 'candidate_id' => $target->id,
                'reviewer_player_id' => $reviewerPlayerId, 'assigned_by_player_id' => $actorPlayerId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }

    private function copyTags(string $allianceId, RecruitmentCandidate $source, RecruitmentCandidate $target): void
    {
        $tagIds = DB::table('recruitment_candidate_tags')->where('candidate_id', $source->id)->orderBy('tag_id')->pluck('tag_id');
        foreach ($tagIds as $tagId) {
            DB::table('recruitment_candidate_tags')->insertOrIgnore([
                'alliance_id' => $allianceId, 'candidate_id' => $target->id, 'tag_id' => $tagId,
                'created_at' => now(), 'updated_at' => now(),
            ]);
        }
    }
}
