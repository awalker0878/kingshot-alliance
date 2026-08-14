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

        DB::transaction(function () use ($actor, $alliance, $candidate, $reviewer): void {
            $currentAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            $lockedActor = Player::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $this->authorization->allowsForUpdate($lockedActor, $currentAlliance, PermissionKey::RecruitmentManage)) {
                throw new AuthorizationException('You are no longer allowed to assign recruitment reviewers.');
            }

            $lockedCandidate = RecruitmentCandidate::query()
                ->where('alliance_id', $currentAlliance->id)
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedReviewer = Player::query()
                ->whereKey($reviewer->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $lockedReviewer->current_kingdom_id !== (string) $currentAlliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must currently belong to the Alliance Kingdom.',
                ]);
            }

            $reviewerMembership = AllianceMembership::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('player_id', $lockedReviewer->id)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if (! $reviewerMembership instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must be active Alliance Players.',
                ]);
            }

            $existing = DB::table('recruitment_candidate_reviewers')
                ->where('candidate_id', $lockedCandidate->id)
                ->where('reviewer_player_id', $lockedReviewer->id)
                ->lockForUpdate()
                ->first();

            if ($existing !== null) {
                return;
            }

            DB::table('recruitment_candidate_reviewers')->insert([
                'id' => (string) Str::ulid(),
                'alliance_id' => $currentAlliance->id,
                'candidate_id' => $lockedCandidate->id,
                'reviewer_player_id' => $lockedReviewer->id,
                'assigned_by_player_id' => $lockedActor->id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);

            $this->audit->record('recruitment.reviewer.assigned', $lockedActor, $lockedCandidate, $currentAlliance, [
                'reviewer_player_id' => $lockedReviewer->id,
            ]);
            $this->outbox->record('recruitment.reviewer.assigned', (string) $currentAlliance->id, $lockedCandidate, [
                'candidate_id' => $lockedCandidate->id,
                'reviewer_player_id' => $lockedReviewer->id,
            ]);
        });
    }
}
