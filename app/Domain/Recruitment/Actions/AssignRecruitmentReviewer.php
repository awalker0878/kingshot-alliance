<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Services\RecruitmentOutbox;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssignRecruitmentReviewer
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        AllianceMembership $reviewer,
    ): void {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to assign recruitment reviewers.');
        }

        if ($candidate->alliance_id !== $alliance->id || $reviewer->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate and reviewer must belong to the active alliance.');
        }

        if ($reviewer->status !== MembershipStatus::Active) {
            throw ValidationException::withMessages(['reviewer' => 'Recruitment reviewers must be active alliance members.']);
        }

        DB::transaction(function () use ($actor, $alliance, $candidate, $reviewer): void {
            $existing = DB::table('recruitment_candidate_reviewers')
                ->where('candidate_id', $candidate->id)
                ->where('membership_id', $reviewer->id)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                return;
            }

            DB::table('recruitment_candidate_reviewers')->insert([
                'id' => (string) Str::ulid(),
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'membership_id' => $reviewer->id,
                'assigned_by_user_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('recruitment.reviewer.assigned', $actor, $candidate, $alliance, [
                'reviewer_membership_id' => $reviewer->id,
            ]);
            $this->outbox->record('recruitment.reviewer.assigned', $alliance, $candidate, [
                'candidate_id' => $candidate->id,
                'reviewer_membership_id' => $reviewer->id,
            ]);
        });
    }
}
