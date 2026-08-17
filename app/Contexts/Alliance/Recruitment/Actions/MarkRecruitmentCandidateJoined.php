<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class MarkRecruitmentCandidateJoined
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(OutboxPublished $event): void
    {
        if ($event->eventType !== 'invitation.accepted') {
            return;
        }

        $invitationId = $event->payload['invitation_id'] ?? null;
        $playerId = $event->payload['player_id'] ?? null;
        if (! is_string($invitationId) || $invitationId === '' || ! is_string($playerId) || $playerId === '') {
            return;
        }

        DB::transaction(function () use ($event, $invitationId, $playerId): void {
            // This is an event projection, not a new human-authorized mutation. The
            // durable invitation.accepted event is the cause, but the projection still
            // follows lifecycle -> aggregate lock order and verifies the captured Player.
            $alliance = Alliance::query()
                ->whereKey($event->allianceId)
                ->sharedLock()
                ->first();

            if (! $alliance instanceof Alliance) {
                return;
            }

            $candidate = RecruitmentCandidate::query()
                ->where('alliance_id', $alliance->id)
                ->where('membership_invitation_id', $invitationId)
                ->whereNull('merged_into_id')
                ->lockForUpdate()
                ->first();

            if (! $candidate instanceof RecruitmentCandidate || $candidate->stage === RecruitmentStage::Joined) {
                return;
            }

            if ($candidate->stage !== RecruitmentStage::Accepted
                || $candidate->player_id === null
                || (string) $candidate->player_id !== $playerId) {
                return;
            }

            $actor = Player::query()->whereKey($playerId)->first();
            if (! $actor instanceof Player) {
                return;
            }

            $now = now();
            $candidate->forceFill([
                'stage' => RecruitmentStage::Joined,
                'joined_at' => $now,
                'retention_due_at' => null,
                'updated_by_player_id' => $actor->id,
            ])->save();

            RecruitmentStageHistory::query()->create([
                'alliance_id' => $alliance->id,
                'candidate_id' => $candidate->id,
                'from_stage' => RecruitmentStage::Accepted,
                'to_stage' => RecruitmentStage::Joined,
                'reason' => 'Alliance invitation accepted',
                'changed_by_player_id' => $actor->id,
                'changed_at' => $now,
            ]);

            $this->audit->record('recruitment.candidate.joined', $actor, $candidate, $alliance, [
                'membership_invitation_id' => $invitationId,
            ]);
            $this->outbox->record('recruitment.candidate.joined', (string) $alliance->id, $candidate, [
                'candidate_id' => $candidate->id,
                'membership_invitation_id' => $invitationId,
            ]);
        });
    }
}
