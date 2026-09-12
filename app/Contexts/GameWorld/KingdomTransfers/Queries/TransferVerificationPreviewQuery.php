<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\Access\Services\TransferAuthorization;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferVerificationPreview;
use Illuminate\Auth\Access\AuthorizationException;
use LogicException;

/** Fixed-cost model hydration and evidence assessment for dashboards and their consumers. */
final readonly class TransferVerificationPreviewQuery
{
    private const ASSESSMENT_LIMIT = 25;

    public function __construct(
        private TransferAuthorization $authorization,
        private TransferPlanQuery $plans,
        private TransferEligibilityQuery $eligibility,
    ) {}

    public function current(string $actorPlayerId, string $allianceId): ?TransferVerificationPreview
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, TransferPermission::View)) {
            throw new AuthorizationException;
        }
        $plan = $this->plans->currentForAlliance($allianceId);
        if ($plan === null) {
            return null;
        }
        // SQL totals cover the entire active plan in the same statement snapshot.
        // LIMIT bounds hydrated rows, not these totals. No relationship graph is loaded.
        $rows = TransferParticipant::query()->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $plan->id)->whereNull('withdrawn_at')
            ->select('transfer_participants.*')
            ->selectRaw('count(*) over () as preview_total')
            ->selectRaw('sum(case when readiness_state = ? then 1 else 0 end) over () as preview_manual_blocked', [TransferReadinessState::Blocked->value])
            ->orderBy('id')->limit(self::ASSESSMENT_LIMIT)->get();
        $total = (int) ($rows->first()?->getAttribute('preview_total') ?? 0);
        $manualBlocked = (int) ($rows->first()?->getAttribute('preview_manual_blocked') ?? 0);
        $windowAvailable = $plan->window instanceof TransferWindow
            && (string) $plan->window->alliance_id === $allianceId;
        $knownAffected = $manualBlocked;
        $affected = [];
        if ($windowAvailable) {
            $assessments = $this->eligibility->forPlan($allianceId, $plan, $rows);
            foreach ($rows as $participant) {
                $manual = $participant->readiness_state === TransferReadinessState::Blocked;
                $assessment = $assessments[(string) $participant->id]['assessment'] ?? null;
                if (! $assessment instanceof TransferEligibilityAssessment) {
                    throw new LogicException('Canonical Transfer assessment is missing for a selected participant.');
                }
                $attention = in_array($assessment->outcome,
                    [TransferEligibilityOutcome::Blocked, TransferEligibilityOutcome::NeedsVerification], true);
                if ($manual || $attention) {
                    $affected[] = (string) $participant->id;
                    // Manual blockers already have an exact whole-plan count; never count twice.
                    if (! $manual) {
                        $knownAffected++;
                    }
                }
            }
        }

        return new TransferVerificationPreview((string) $plan->id, $plan->updated_at?->toIso8601String(),
            $windowAvailable, $total, $windowAvailable ? $rows->count() : 0, $manualBlocked, $knownAffected, $affected);
    }
}
