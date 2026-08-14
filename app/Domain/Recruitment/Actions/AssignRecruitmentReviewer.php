<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssignRecruitmentReviewer
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        Player $reviewer,
    ): void {
        DB::transaction(function () use ($actor, $alliance, $candidate, $reviewer): void {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            // Reviewer assignment is a child mutation: share-lock candidate state so
            // independent candidate children can proceed while merge remains exclusive.
            $currentCandidate = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($candidate->id)
                ->sharedLock()
                ->firstOrFail();

            if ($currentCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Reviewers must be assigned to the current merged candidate record.',
                ]);
            }

            $lockedReviewer = Player::query()
                ->whereKey($reviewer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedReviewer->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must currently belong to the Alliance Kingdom.',
                ]);
            }

            $reviewerMembership = AllianceMembership::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $lockedReviewer->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if (! $reviewerMembership instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must be active Alliance Players.',
                ]);
            }

            // The unique(candidate_id, reviewer_player_id) constraint is the atomic
            // duplicate-assignment boundary; do not lock a row that may not exist.
            $inserted = DB::table('recruitment_candidate_reviewers')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'alliance_id' => $context->alliance->id,
                'candidate_id' => $currentCandidate->id,
                'reviewer_player_id' => $lockedReviewer->id,
                'assigned_by_player_id' => $context->actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if (! $inserted) {
                return;
            }

            $this->audit->record('recruitment.reviewer.assigned', $context->actor, $currentCandidate, $context->alliance, [
                'reviewer_player_id' => $lockedReviewer->id,
            ]);
            $this->outbox->record('recruitment.reviewer.assigned', (string) $context->alliance->id, $currentCandidate, [
                'candidate_id' => $currentCandidate->id,
                'reviewer_player_id' => $lockedReviewer->id,
            ]);
        });
    }
}
