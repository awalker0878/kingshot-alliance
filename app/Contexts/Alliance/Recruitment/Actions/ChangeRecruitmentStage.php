<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Recruitment\Enums\RecruitmentStage;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentSetting;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentStageHistory;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRecruitmentStage
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
        RecruitmentCandidate $candidate,
        RecruitmentStage $target,
        ?string $reason = null,
        ?CarbonImmutable $nextActionAt = null,
    ): RecruitmentCandidate {
        return DB::transaction(function () use ($actor, $alliance, $candidate, $target, $reason, $nextActionAt): RecruitmentCandidate {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, AlliancePermission::RecruitmentManage);

            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->merged_into_id !== null) {
                throw ValidationException::withMessages([
                    'candidate' => 'Change the stage on the current merged candidate record.',
                ]);
            }

            $from = $locked->stage;
            if ($from === $target) {
                return $locked;
            }

            if (! $from->canTransitionTo($target)) {
                throw ValidationException::withMessages([
                    'stage' => sprintf('A candidate cannot move directly from %s to %s.', $from->value, $target->value),
                ]);
            }

            if ($target === RecruitmentStage::Joined && $locked->membership_invitation_id === null) {
                throw ValidationException::withMessages([
                    'stage' => 'A candidate must be converted to an alliance invitation before being marked joined.',
                ]);
            }

            // Retention policy is configuration state consumed by this transition.
            // Share-lock it so a concurrent settings update cannot change the policy
            // midway through a candidate transition.
            $settings = RecruitmentSetting::query()
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->first();
            $retentionDays = $settings instanceof RecruitmentSetting
                ? $settings->retention_unsuccessful_days
                : 90;
            $now = now();

            $updates = [
                'stage' => $target,
                'next_action_at' => $nextActionAt?->utc(),
                'updated_by_player_id' => $context->actor->id,
                'retention_due_at' => $target->isUnsuccessful() ? $now->copy()->addDays($retentionDays) : null,
            ];

            if ($locked->first_responded_at === null && $target !== RecruitmentStage::New) {
                $updates['first_responded_at'] = $now;
            }

            if (in_array($target, [RecruitmentStage::Screening, RecruitmentStage::Contacted, RecruitmentStage::Interview], true)) {
                $updates += [
                    'accepted_at' => null,
                    'declined_at' => null,
                    'withdrawn_at' => null,
                    'joined_at' => null,
                ];
            }

            if ($target === RecruitmentStage::Accepted) {
                $updates += [
                    'accepted_at' => $now,
                    'declined_at' => null,
                    'withdrawn_at' => null,
                ];
            }

            if ($target === RecruitmentStage::Declined) {
                $updates += [
                    'accepted_at' => null,
                    'declined_at' => $now,
                    'withdrawn_at' => null,
                ];
            }

            if ($target === RecruitmentStage::Withdrawn) {
                $updates += [
                    'accepted_at' => null,
                    'declined_at' => null,
                    'withdrawn_at' => $now,
                ];
            }

            if ($target === RecruitmentStage::Joined) {
                $updates += [
                    'joined_at' => $now,
                    'retention_due_at' => null,
                ];
            }

            $locked->forceFill($updates)->save();

            RecruitmentStageHistory::query()->create([
                'alliance_id' => $context->alliance->id,
                'candidate_id' => $locked->id,
                'from_stage' => $from,
                'to_stage' => $target,
                'reason' => $reason === null ? null : trim($reason),
                'changed_by_player_id' => $context->actor->id,
                'changed_at' => $now,
            ]);

            $this->audit->record('recruitment.candidate.stage_changed', $context->actor, $locked, $context->alliance, [
                'from_stage' => $from->value,
                'to_stage' => $target->value,
            ]);
            $this->outbox->record('recruitment.candidate.stage_changed', (string) $context->alliance->id, $locked, [
                'candidate_id' => $locked->id,
                'from_stage' => $from->value,
                'to_stage' => $target->value,
            ]);

            return $locked->refresh();
        });
    }
}
