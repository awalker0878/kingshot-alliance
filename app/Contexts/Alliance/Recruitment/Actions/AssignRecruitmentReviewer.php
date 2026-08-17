<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
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
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $candidateId,
        string $reviewerPlayerId,
    ): void {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $candidateId, $reviewerPlayerId): void {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $currentCandidate = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($candidateId)
                ->sharedLock()
                ->firstOrFail();

            if ($currentCandidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Reviewers must be assigned to the current merged candidate record.',
                ]);
            }

            $reviewer = $this->players->require($reviewerPlayerId);
            if ($reviewer->kingdomId !== (string) $context->alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must currently belong to the Alliance Kingdom.',
                ]);
            }

            $reviewerMembership = AllianceMembership::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('player_id', $reviewerPlayerId)
                ->where('status', MembershipStatus::Active->value)
                ->lockForUpdate()
                ->first();

            if (! $reviewerMembership instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'reviewer_player_id' => 'Recruitment reviewers must be active Alliance Players.',
                ]);
            }

            $inserted = DB::table('recruitment_candidate_reviewers')->insertOrIgnore([
                'id' => (string) Str::ulid(),
                'alliance_id' => $context->alliance->id,
                'candidate_id' => $currentCandidate->id,
                'reviewer_player_id' => $reviewerPlayerId,
                'assigned_by_player_id' => $context->actor->playerId,
                'created_at' => now(),
                'updated_at' => now(),
            ]) === 1;

            if (! $inserted) {
                return;
            }

            $this->audit->record('recruitment.reviewer.assigned', $context->actor, $currentCandidate, $context->alliance, [
                'reviewer_player_id' => $reviewerPlayerId,
            ]);
            $this->outbox->record('recruitment.reviewer.assigned', (string) $context->alliance->id, $currentCandidate, [
                'candidate_id' => $currentCandidate->id,
                'reviewer_player_id' => $reviewerPlayerId,
            ]);
        });
    }
}
