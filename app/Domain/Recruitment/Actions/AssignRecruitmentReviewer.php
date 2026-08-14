<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssignRecruitmentReviewer
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        Player $reviewer,
    ): void {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to assign recruitment reviewers.');
        }

        if ($candidate->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate must belong to the active Alliance.');
        }

        $reviewerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $reviewer->id)
            ->where('status', MembershipStatus::Active->value)
            ->first();
        if (! $reviewerMembership instanceof AllianceMembership) {
            throw ValidationException::withMessages(['reviewer_player_id' => 'Recruitment reviewers must be active Alliance Players.']);
        }

        DB::transaction(function () use ($actor, $alliance, $candidate, $reviewer): void {
            $existing = DB::table('recruitment_candidate_reviewers')
                ->where('candidate_id', $candidate->id)
                ->where('reviewer_player_id', $reviewer->id)
                ->lockForUpdate()
                ->exists();

            if ($existing) {
                return;
            }

            DB::table('recruitment_candidate_reviewers')->insert([
                'id' => (string) Str::ulid(),
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'reviewer_player_id' => $reviewer->id,
                'assigned_by_player_id' => $actor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('recruitment.reviewer.assigned', $actor, $candidate, $alliance, [
                'reviewer_player_id' => $reviewer->id,
            ]);
            $this->outbox->record('recruitment.reviewer.assigned', (string) $alliance->id, $candidate, [
                'candidate_id' => $candidate->id,
                'reviewer_player_id' => $reviewer->id,
            ]);
        });
    }
}
