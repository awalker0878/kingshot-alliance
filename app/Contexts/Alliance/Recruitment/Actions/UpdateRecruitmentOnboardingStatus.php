<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class UpdateRecruitmentOnboardingStatus
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
        RecruitmentCandidateOnboarding $onboarding,
        RecruitmentOnboardingStatus $status,
    ): RecruitmentCandidateOnboarding {
        return DB::transaction(function () use ($actor, $alliance, $onboarding, $status): RecruitmentCandidateOnboarding {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

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
