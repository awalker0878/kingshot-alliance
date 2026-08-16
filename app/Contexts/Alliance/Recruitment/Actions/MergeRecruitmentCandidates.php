<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentNote;
use App\Contexts\GameWorld\Models\Player;
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
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidate $source,
        RecruitmentCandidate $target,
        ?string $reason = null,
    ): RecruitmentCandidate {
        if ((string) $source->id === (string) $target->id) {
            throw ValidationException::withMessages(['candidate' => 'A recruitment candidate cannot be merged into itself.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $source, $target, $reason): RecruitmentCandidate {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            // Candidate-wide merge state is serialized by locking both candidate
            // aggregates in deterministic id order.
            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereIn('id', [$source->id, $target->id])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->keyBy('id');

            /** @var RecruitmentCandidate|null $sourceCandidate */
            $sourceCandidate = $locked->get($source->id);
            /** @var RecruitmentCandidate|null $targetCandidate */
            $targetCandidate = $locked->get($target->id);

            if (! $sourceCandidate instanceof RecruitmentCandidate || ! $targetCandidate instanceof RecruitmentCandidate) {
                throw ValidationException::withMessages([
                    'candidate' => 'Both recruitment candidates must belong to the active Alliance.',
                ]);
            }

            if ((string) $sourceCandidate->merged_into_id === (string) $targetCandidate->id) {
                return $targetCandidate;
            }

            if ($sourceCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['source' => 'The source candidate has already been merged.']);
            }

            if ($targetCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['target' => 'A candidate that was merged into another record cannot be the merge target.']);
            }

            $this->copyReviewers($context->alliance, $sourceCandidate, $targetCandidate, $context->actor);
            $this->copyTags($context->alliance, $sourceCandidate, $targetCandidate);

            $targetCandidate->forceFill([
                'contact_handle' => $targetCandidate->contact_handle ?: $sourceCandidate->contact_handle,
                'source' => $targetCandidate->source ?: $sourceCandidate->source,
                'next_action_at' => $targetCandidate->next_action_at ?: $sourceCandidate->next_action_at,
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            if ($reason !== null && trim($reason) !== '') {
                RecruitmentNote::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'candidate_id' => $targetCandidate->id,
                    'author_player_id' => $context->actor->id,
                    'body' => 'Merge reason: '.trim($reason),
                ]);
            }

            $sourceCandidate->forceFill([
                'merged_into_id' => $targetCandidate->id,
                'next_action_at' => null,
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $this->audit->record('recruitment.candidate.merged', $context->actor, $sourceCandidate, $context->alliance, [
                'source_candidate_id' => $sourceCandidate->id,
                'target_candidate_id' => $targetCandidate->id,
            ]);
            $this->outbox->record('recruitment.candidate.merged', (string) $context->alliance->id, $sourceCandidate, [
                'source_candidate_id' => $sourceCandidate->id,
                'target_candidate_id' => $targetCandidate->id,
            ]);

            return $targetCandidate->refresh();
        });
    }

    private function copyReviewers(
        Alliance $alliance,
        RecruitmentCandidate $source,
        RecruitmentCandidate $target,
        Player $actor,
    ): void {
        $reviewerIds = DB::table('recruitment_candidate_reviewers')
            ->where('candidate_id', $source->id)
            ->orderBy('reviewer_player_id')
            ->pluck('reviewer_player_id');

        foreach ($reviewerIds as $reviewerPlayerId) {
            DB::table('recruitment_candidate_reviewers')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'alliance_id' => $alliance->id,
                'candidate_id' => $target->id,
                'reviewer_player_id' => $reviewerPlayerId,
                'assigned_by_player_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }

    private function copyTags(Alliance $alliance, RecruitmentCandidate $source, RecruitmentCandidate $target): void
    {
        $tagIds = DB::table('recruitment_candidate_tags')
            ->where('candidate_id', $source->id)
            ->orderBy('tag_id')
            ->pluck('tag_id');

        foreach ($tagIds as $tagId) {
            DB::table('recruitment_candidate_tags')->insertOrIgnore([
                'alliance_id' => $alliance->id,
                'candidate_id' => $target->id,
                'tag_id' => $tagId,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }
    }
}
