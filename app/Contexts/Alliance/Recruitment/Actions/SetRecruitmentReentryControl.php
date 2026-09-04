<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SetRecruitmentReentryControl
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $candidateId,
        RecruitmentReentryControl $control,
        ?string $reason = null,
        ?string $reviewAt = null,
    ): string {
        $reason = $reason === null || trim($reason) === '' ? null : trim($reason);
        $review = $reviewAt === null || trim($reviewAt) === '' ? null : Carbon::parse($reviewAt);
        if ($control === RecruitmentReentryControl::ReapplyAfter && $review === null) {
            throw ValidationException::withMessages(['review_at' => 'A reapply-after control requires a review date.']);
        }
        if ($control === RecruitmentReentryControl::Normal) {
            $reason = null;
            $review = null;
        }

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $candidateId, $control, $reason, $review): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::RecruitmentManage);
            $candidate = RecruitmentCandidate::query()
                ->whereKey($candidateId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($candidate->merged_into_id !== null || $candidate->anonymized_at !== null) {
                throw ValidationException::withMessages(['candidate' => 'This recruitment record cannot receive a re-entry control.']);
            }

            $before = [
                'control' => $candidate->reentry_control->value,
                'reason' => $candidate->reentry_reason,
                'review_at' => $candidate->reentry_review_at?->toIso8601String(),
            ];
            $after = [
                'control' => $control->value,
                'reason' => $reason,
                'review_at' => $review?->toIso8601String(),
            ];
            if ($before === $after) {
                return (string) $candidate->id;
            }

            $candidate->forceFill([
                'reentry_control' => $control,
                'reentry_reason' => $reason,
                'reentry_review_at' => $review,
                'reentry_set_by_player_id' => $control === RecruitmentReentryControl::Normal ? null : $actorPlayerId,
                'reentry_set_at' => $control === RecruitmentReentryControl::Normal ? null : now(),
                'updated_by_player_id' => $actorPlayerId,
            ])->save();

            $metadata = ['candidate_id' => (string) $candidate->id, 'from' => $before, 'to' => $after];
            $this->audit->record('recruitment.reentry_control_changed', $context->actor, $candidate, $context->alliance, $metadata);
            $this->outbox->record('recruitment.reentry_control_changed', $allianceId, $candidate, $metadata);

            return (string) $candidate->id;
        });
    }
}
