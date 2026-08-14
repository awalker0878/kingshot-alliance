<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRecruitmentOnboardingStatus
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        RecruitmentCandidateOnboarding $onboarding,
        RecruitmentOnboardingStatus $status,
    ): RecruitmentCandidateOnboarding {
        return DB::transaction(function () use ($actor, $alliance, $onboarding, $status): RecruitmentCandidateOnboarding {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RecruitmentManage);

            $candidate = RecruitmentCandidate::query()
                ->whereKey($onboarding->candidate_id)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($candidate->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Update onboarding on the current merged candidate record.',
                ]);
            }

            $locked = RecruitmentCandidateOnboarding::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('candidate_id', $candidate->id)
                ->whereKey($onboarding->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked->forceFill([
                'status' => $status,
                'completed_at' => $status === RecruitmentOnboardingStatus::Completed ? now() : null,
                'completed_by_player_id' => $status === RecruitmentOnboardingStatus::Completed ? $context->actor->id : null,
            ])->save();

            $this->audit->record('recruitment.onboarding.updated', $context->actor, $locked, $context->alliance, [
                'candidate_id' => $locked->candidate_id,
                'status' => $status->value,
            ]);
            $this->outbox->record('recruitment.onboarding.updated', (string) $context->alliance->id, $locked, [
                'candidate_id' => $locked->candidate_id,
                'status' => $status->value,
            ]);

            return $locked->refresh();
        });
    }
}
