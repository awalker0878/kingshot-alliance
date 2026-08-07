<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Models\Alliance;
use App\Models\RecruitmentCommunication;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class MarkRecruitmentCommunicationSent
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCommunication $communication,
    ): RecruitmentCommunication {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to update recruitment communications.');
        }

        if ($communication->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The recruitment communication belongs to another alliance.');
        }

        return DB::transaction(function () use ($actor, $alliance, $communication): RecruitmentCommunication {
            $locked = RecruitmentCommunication::query()
                ->where('alliance_id', $alliance->id)
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

            $this->audit->record('recruitment.communication.sent', $actor, $locked, $alliance, [
                'candidate_id' => $locked->candidate_id,
            ]);
            $this->outbox->record('recruitment.communication.sent', $alliance, $locked, [
                'candidate_id' => $locked->candidate_id,
            ]);

            return $locked->refresh();
        });
    }
}
