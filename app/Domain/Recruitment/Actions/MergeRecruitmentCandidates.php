<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentNote;
use App\Domain\Recruitment\Services\RecruitmentOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class MergeRecruitmentCandidates
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidate $source,
        RecruitmentCandidate $target,
        ?string $reason = null,
    ): RecruitmentCandidate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to merge recruitment candidates.');
        }

        if ($source->alliance_id !== $alliance->id || $target->alliance_id !== $alliance->id) {
            throw new AuthorizationException('Both recruitment candidates must belong to the active alliance.');
        }

        if ($source->id === $target->id) {
            throw ValidationException::withMessages(['candidate' => 'A recruitment candidate cannot be merged into itself.']);
        }

        return DB::transaction(function () use ($actor, $alliance, $source, $target, $reason): RecruitmentCandidate {
            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $alliance->id)
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
                throw new AuthorizationException('Both recruitment candidates must belong to the active alliance.');
            }

            if ($sourceCandidate->merged_into_id === $targetCandidate->id) {
                return $targetCandidate;
            }

            if ($sourceCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['source' => 'The source candidate has already been merged.']);
            }

            if ($targetCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages(['target' => 'A candidate that was merged into another record cannot be the merge target.']);
            }

            $this->copyReviewers($alliance, $sourceCandidate, $targetCandidate, $actor);
            $this->copyTags($alliance, $sourceCandidate, $targetCandidate);

            $targetCandidate->forceFill([
                'contact_handle' => $targetCandidate->contact_handle ?: $sourceCandidate->contact_handle,
                'source' => $targetCandidate->source ?: $sourceCandidate->source,
                'next_action_at' => $targetCandidate->next_action_at ?: $sourceCandidate->next_action_at,
                'updated_by_user_id' => $actor->id,
            ])->save();

            if ($reason !== null && trim($reason) !== '') {
                RecruitmentNote::query()->create([
                    'alliance_id' => $alliance->id,
                    'candidate_id' => $targetCandidate->id,
                    'author_membership_id' => $this->actorMembershipId($actor, $alliance),
                    'body' => 'Merge reason: '.trim($reason),
                ]);
            }

            $sourceCandidate->forceFill([
                'merged_into_id' => $targetCandidate->id,
                'next_action_at' => null,
                'updated_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('recruitment.candidate.merged', $actor, $sourceCandidate, $alliance, [
                'source_candidate_id' => $sourceCandidate->id,
                'target_candidate_id' => $targetCandidate->id,
            ]);
            $this->outbox->record('recruitment.candidate.merged', $alliance, $sourceCandidate, [
                'source_candidate_id' => $sourceCandidate->id,
                'target_candidate_id' => $targetCandidate->id,
            ]);

            return $targetCandidate->refresh();
        });
    }

    private function actorMembershipId(User $actor, Alliance $alliance): string
    {
        return (string) DB::table('alliance_memberships')
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $actor->id)
            ->where('status', 'active')
            ->value('id');
    }

    private function copyReviewers(
        Alliance $alliance,
        RecruitmentCandidate $source,
        RecruitmentCandidate $target,
        User $actor,
    ): void {
        $reviewerIds = DB::table('recruitment_candidate_reviewers')
            ->where('candidate_id', $source->id)
            ->pluck('membership_id');

        foreach ($reviewerIds as $membershipId) {
            $exists = DB::table('recruitment_candidate_reviewers')
                ->where('candidate_id', $target->id)
                ->where('membership_id', $membershipId)
                ->exists();

            if (! $exists) {
                DB::table('recruitment_candidate_reviewers')->insert([
                    'id' => (string) Str::ulid(),
                    'alliance_id' => $alliance->id,
                    'candidate_id' => $target->id,
                    'membership_id' => $membershipId,
                    'assigned_by_user_id' => $actor->id,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);
            }
        }
    }

    private function copyTags(Alliance $alliance, RecruitmentCandidate $source, RecruitmentCandidate $target): void
    {
        $tagIds = DB::table('recruitment_candidate_tags')
            ->where('candidate_id', $source->id)
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
