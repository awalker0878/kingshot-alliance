<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Services;

use App\Contexts\Alliance\Recruitment\Enums\RecruitmentReentryControl;
use App\Contexts\Alliance\Recruitment\Models\RecruitmentCandidate;
use Illuminate\Validation\ValidationException;

final class RecruitmentReentryPolicy
{
    public function isBlocking(RecruitmentCandidate $candidate): bool
    {
        return match ($candidate->reentry_control) {
            RecruitmentReentryControl::Normal => false,
            RecruitmentReentryControl::DoNotInvite, RecruitmentReentryControl::ReviewRequired => true,
            RecruitmentReentryControl::ReapplyAfter => $candidate->reentry_review_at === null || $candidate->reentry_review_at->isFuture(),
        };
    }

    public function assertCanConvert(RecruitmentCandidate $candidate): void
    {
        if (! $this->isBlocking($candidate)) return;

        $message = match ($candidate->reentry_control) {
            RecruitmentReentryControl::DoNotInvite => 'This candidate is marked do not invite for this Alliance.',
            RecruitmentReentryControl::ReviewRequired => 'This candidate requires officer review before invitation.',
            RecruitmentReentryControl::ReapplyAfter => $candidate->reentry_review_at === null
                ? 'This candidate cannot be invited until a reapply date is reviewed.'
                : 'This candidate cannot be invited before '.$candidate->reentry_review_at->toDateString().'.',
            RecruitmentReentryControl::Normal => 'This candidate is available for normal recruiting workflow.',
        };

        throw ValidationException::withMessages(['candidate' => $message]);
    }

    /** @return array{control:RecruitmentReentryControl,reason:?string,reviewAt:?\Illuminate\Support\Carbon,setBy:?string,setAt:?\Illuminate\Support\Carbon} */
    public function stricter(RecruitmentCandidate $a, RecruitmentCandidate $b): array
    {
        $candidates = [$a, $b];
        usort($candidates, function (RecruitmentCandidate $left, RecruitmentCandidate $right): int {
            $leftSeverity = $this->effectiveSeverity($left);
            $rightSeverity = $this->effectiveSeverity($right);
            if ($leftSeverity !== $rightSeverity) return $rightSeverity <=> $leftSeverity;

            $leftDate = $left->reentry_review_at?->getTimestamp() ?? PHP_INT_MAX;
            $rightDate = $right->reentry_review_at?->getTimestamp() ?? PHP_INT_MAX;
            return $rightDate <=> $leftDate;
        });
        $winner = $candidates[0];
        if ($this->effectiveSeverity($winner) === 0) {
            return ['control' => RecruitmentReentryControl::Normal, 'reason' => null, 'reviewAt' => null, 'setBy' => null, 'setAt' => null];
        }

        return [
            'control' => $winner->reentry_control,
            'reason' => $winner->reentry_reason === null ? null : (string) $winner->reentry_reason,
            'reviewAt' => $winner->reentry_review_at,
            'setBy' => $winner->reentry_set_by_player_id === null ? null : (string) $winner->reentry_set_by_player_id,
            'setAt' => $winner->reentry_set_at,
        ];
    }

    private function effectiveSeverity(RecruitmentCandidate $candidate): int
    {
        if ($candidate->reentry_control === RecruitmentReentryControl::ReapplyAfter
            && $candidate->reentry_review_at !== null
            && $candidate->reentry_review_at->isPast()) {
            return 0;
        }
        return $candidate->reentry_control->severity();
    }
}
