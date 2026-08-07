<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use App\Domain\Recruitment\Models\RecruitmentSetting;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ChangeRecruitmentStage
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RecruitmentCandidate $candidate,
        RecruitmentStage $target,
        ?string $reason = null,
        ?CarbonImmutable $nextActionAt = null,
    ): RecruitmentCandidate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RecruitmentManage)) {
            throw new AuthorizationException('You are not allowed to change recruitment stages.');
        }

        if ($candidate->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The candidate belongs to another alliance.');
        }

        return DB::transaction(function () use ($actor, $alliance, $candidate, $target, $reason, $nextActionAt): RecruitmentCandidate {
            $locked = RecruitmentCandidate::query()
                ->where('alliance_id', $alliance->id)
                ->whereKey($candidate->id)
                ->lockForUpdate()
                ->firstOrFail();

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

            $settings = RecruitmentSetting::query()->where('alliance_id', $alliance->id)->first();
            $retentionDays = $settings instanceof RecruitmentSetting
                ? $settings->retention_unsuccessful_days
                : 90;
            $now = now();

            $updates = [
                'stage' => $target,
                'next_action_at' => $nextActionAt?->utc(),
                'updated_by_user_id' => $actor->id,
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
                'alliance_id' => $alliance->id,
                'candidate_id' => $locked->id,
                'from_stage' => $from,
                'to_stage' => $target,
                'reason' => $reason === null ? null : trim($reason),
                'changed_by_user_id' => $actor->id,
                'changed_at' => $now,
            ]);

            $this->audit->record('recruitment.candidate.stage_changed', $actor, $locked, $alliance, [
                'from_stage' => $from->value,
                'to_stage' => $target->value,
            ]);
            $this->outbox->record('recruitment.candidate.stage_changed', (string) $alliance->id, $locked, [
                'candidate_id' => $locked->id,
                'from_stage' => $from->value,
                'to_stage' => $target->value,
            ]);

            return $locked->refresh();
        });
    }
}
