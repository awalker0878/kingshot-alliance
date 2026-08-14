<?php

declare(strict_types=1);

namespace App\Domain\Recruitment\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Support\Facades\DB;

final class PurgeExpiredRecruitmentCandidates
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $candidateIds = RecruitmentCandidate::query()
            ->whereIn('stage', [RecruitmentStage::Declined->value, RecruitmentStage::Withdrawn->value])
            ->whereNull('anonymized_at')
            ->whereNotNull('retention_due_at')
            ->where('retention_due_at', '<=', now())
            ->orderBy('retention_due_at')
            ->limit(max(1, min($limit, 1000)))
            ->pluck('id');

        $anonymized = 0;

        foreach ($candidateIds as $candidateId) {
            $changed = DB::transaction(function () use ($candidateId): bool {
                // Resolve immutable routing state without a lock, then acquire the
                // same Alliance -> candidate order used by interactive Recruitment writes.
                $routing = RecruitmentCandidate::query()
                    ->select(['id', 'alliance_id'])
                    ->whereKey($candidateId)
                    ->first();

                if (! $routing instanceof RecruitmentCandidate) {
                    return false;
                }

                $alliance = Alliance::query()
                    ->whereKey($routing->alliance_id)
                    ->sharedLock()
                    ->first();

                if (! $alliance instanceof Alliance) {
                    return false;
                }

                $candidate = RecruitmentCandidate::query()
                    ->whereKey($routing->id)
                    ->where('alliance_id', $alliance->id)
                    ->whereIn('stage', [RecruitmentStage::Declined->value, RecruitmentStage::Withdrawn->value])
                    ->whereNull('anonymized_at')
                    ->whereNotNull('retention_due_at')
                    ->where('retention_due_at', '<=', now())
                    ->lockForUpdate()
                    ->first();

                if (! $candidate instanceof RecruitmentCandidate) {
                    return false;
                }

                $this->audit->record('recruitment.candidate.anonymized', null, $candidate, $alliance, [
                    'stage' => $candidate->stage->value,
                    'retention_due_at' => $candidate->retention_due_at?->toIso8601String(),
                ]);
                $this->outbox->record('recruitment.candidate.anonymized', (string) $alliance->id, $candidate, [
                    'candidate_id' => $candidate->id,
                    'stage' => $candidate->stage->value,
                ]);

                DB::table('recruitment_answers')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_notes')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_communications')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_candidate_reviewers')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_candidate_tags')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_candidate_onboarding')->where('candidate_id', $candidate->id)->delete();
                DB::table('recruitment_stage_history')->where('candidate_id', $candidate->id)->update(['reason' => null]);

                $candidate->forceFill([
                    'applicant_user_id' => null,
                    'application_invite_id' => null,
                    'membership_invitation_id' => null,
                    'full_name' => 'Deleted candidate',
                    'email' => 'deleted+'.strtolower((string) $candidate->id).'@invalid.local',
                    'contact_handle' => null,
                    'next_action_at' => null,
                    'retention_due_at' => null,
                    'anonymized_at' => now(),
                    'updated_by_player_id' => null,
                ])->save();

                return true;
            });

            if ($changed) {
                $anonymized++;
            }
        }

        return $anonymized;
    }
}
