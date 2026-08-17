<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidateOnboarding;
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
        string $actorPlayerId,
        string $allianceId,
        string $onboardingId,
        RecruitmentOnboardingStatus $status,
    ): string {
        return DB::transaction(function () use ($actorPlayerId, $allianceId, $onboardingId, $status): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $onboarding = RecruitmentCandidateOnboarding::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($onboardingId)
                ->lockForUpdate()
                ->firstOrFail();

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

            $onboarding->forceFill([
                'status' => $status,
                'completed_at' => $status === RecruitmentOnboardingStatus::Completed ? now() : null,
                'completed_by_player_id' => $status === RecruitmentOnboardingStatus::Completed ? $context->actor->playerId : null,
            ])->save();

            $this->audit->record('recruitment.onboarding.updated', $context->actor, $onboarding, $context->alliance, [
                'candidate_id' => $onboarding->candidate_id,
                'status' => $status->value,
            ]);
            $this->outbox->record('recruitment.onboarding.updated', (string) $context->alliance->id, $onboarding, [
                'candidate_id' => $onboarding->candidate_id,
                'status' => $status->value,
            ]);

            return (string) $onboarding->id;
        });
    }
}
