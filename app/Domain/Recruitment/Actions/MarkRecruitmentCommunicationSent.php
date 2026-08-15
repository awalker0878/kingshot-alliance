<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCommunication;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class MarkRecruitmentCommunicationSent
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCommunication $communication,
    ): RecruitmentCommunication {
        return DB::transaction(function () use ($actor, $alliance, $communication): RecruitmentCommunication {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            $candidate = RecruitmentCandidate::query()
                ->whereKey($communication->candidate_id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($candidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Communication state must be updated on the current merged candidate record.',
                ]);
            }

            $locked = RecruitmentCommunication::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('candidate_id', $candidate->id)
                ->whereKey($communication->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === RecruitmentCommunicationStatus::Sent) {
                return $locked;
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

            return $locked->refresh();
        });
    }
}
