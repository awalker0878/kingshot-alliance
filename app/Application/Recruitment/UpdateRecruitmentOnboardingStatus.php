<?php

declare(strict_types=1);

namespace App\Application\Recruitment;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Models\Alliance;
use App\Models\RecruitmentCandidateOnboarding;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final class UpdateRecruitmentOnboardingStatus
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private RecruitmentOutbox $outbox,
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
            $this->outbox->record('recruitment.onboarding.updated', $alliance, $locked, [
                'candidate_id' => $locked->candidate_id,
                'status' => $status->value,
            ]);

            return $locked->refresh();
        });
    }
}
