<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final class AssignRecruitmentReviewer
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
        RecruitmentCandidate $candidate,
        Player $reviewer,
    ): void {
        DB::transaction(function () use ($actor, $alliance, $candidate, $reviewer): void {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

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
