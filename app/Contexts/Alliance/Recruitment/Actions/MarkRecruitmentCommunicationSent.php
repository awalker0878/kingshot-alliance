<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCommunication;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarkRecruitmentCommunicationSent
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $communicationId): string
    {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $communicationId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $locked = RecruitmentCommunication::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($communicationId)
                ->lockForUpdate()
                ->firstOrFail();

            $candidate = RecruitmentCandidate::query()
                ->whereKey($locked->candidate_id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($candidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Communication state must be updated on the current merged candidate record.',
                ]);
            }

            if ($locked->status === RecruitmentCommunicationStatus::Sent) {
                return (string) $locked->id;
            }

            $locked->forceFill([
                'status' => RecruitmentCommunicationStatus::Sent,
                'sent_at' => now(),
                'last_error' => null,
            ])->save();

            $this->audit->record('recruitment.communication.sent', $context->actor, $locked, $context->alliance, [
                'candidate_id' => $locked->candidate_id,
            ]);
            $this->outbox->record('recruitment.communication.sent', (string) $context->alliance->id, $locked, [
                'candidate_id' => $locked->candidate_id,
            ]);

            return (string) $locked->id;
        });
    }
}
