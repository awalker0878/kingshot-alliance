<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class UpdateRecruitmentOnboardingStatus
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidateOnboarding $onboarding,
        RecruitmentOnboardingStatus $status,
    ): RecruitmentCandidateOnboarding {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to update recruitment onboarding.');
        }

        if ($onboarding->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The onboarding item belongs to another alliance.');
        }

        return DB::transaction(function () use ($actor, $alliance, $onboarding, $status): RecruitmentCandidateOnboarding {
            $locked = RecruitmentCandidateOnboarding::query()
                ->where('alliance_id', $alliance->id)
                ->whereKey($onboarding->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'status' => $status,
                'completed_at' => $status === RecruitmentOnboardingStatus::Completed ? now() : null,
                'completed_by_user_id' => $status === RecruitmentOnboardingStatus::Completed ? $actor->id : null,
            ])->save();

            $this->audit->record('recruitment.onboarding.updated', $actor, $locked, $alliance, [
                'candidate_id' => $locked->candidate_id,
                'status' => $status->value,
            ]);
            $this->outbox->record('recruitment.onboarding.updated', (string) $alliance->id, $locked, [
                'candidate_id' => $locked->candidate_id,
                'status' => $status->value,
            ]);

            return $locked->refresh();
        });
    }
}
