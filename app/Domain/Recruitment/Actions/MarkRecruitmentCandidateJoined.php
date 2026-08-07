<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Events\OutboxPublished;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
use App\Domain\Recruitment\Services\RecruitmentOutbox;
use Illuminate\Support\Facades\DB;

final class MarkRecruitmentCandidateJoined
{
    public function __construct(
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== 'invitation.accepted') {
            return;
        }

        $invitationId = $event->payload['invitation_id'] ?? null;
        if (! is_string($invitationId) || $invitationId === '') {
            return;
        }

        DB::transaction(function () use ($event, $invitationId): void {
            $candidate = RecruitmentCandidate::query()
                ->where('alliance_id', $event->allianceId)
                ->where('membership_invitation_id', $invitationId)
                ->whereNull('merged_into_id')
                ->lockForUpdate()
                ->first();

            if (! $candidate instanceof RecruitmentCandidate || $candidate->stage === RecruitmentStage::Joined) {
                return;
            }

            if ($candidate->stage !== RecruitmentStage::Accepted) {
                return;
            }

            $alliance = Alliance::query()->find($event->allianceId);
            if (! $alliance instanceof Alliance) {
                return;
            }

            $userId = $event->payload['user_id'] ?? null;
            $actor = is_int($userId) ? User::query()->find($userId) : null;
            $now = now();

            $candidate->forceFill([
                'stage' => RecruitmentStage::Joined,
                'joined_at' => $now,
                'retention_due_at' => null,
                'updated_by_user_id' => $actor?->id,
            ])->save();

            RecruitmentStageHistory::query()->create([
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'from_stage' => RecruitmentStage::Accepted,
                'to_stage' => RecruitmentStage::Joined,
                'reason' => 'Alliance invitation accepted',
                'changed_by_user_id' => $actor?->id,
                'changed_at' => $now,
            ]);

            $this->audit->record('recruitment.candidate.joined', $actor, $candidate, $alliance, [
                'membership_invitation_id' => $invitationId,
            ]);
            $this->outbox->record('recruitment.candidate.joined', $alliance, $candidate, [
                'candidate_id' => $candidate->id,
                'membership_invitation_id' => $invitationId,
            ]);
        });
    }
}
