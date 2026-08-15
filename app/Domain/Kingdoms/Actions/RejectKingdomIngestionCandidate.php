<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RejectKingdomIngestionCandidate
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $subscriptionId,
        string $candidateId,
    ): KingdomIngestionCandidate {
        return DB::transaction(function () use ($alliance, $actor, $subscriptionId, $candidateId): KingdomIngestionCandidate {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            $route = KingdomIngestionCandidate::query()
                ->select(['id', 'batch_id'])
                ->where('alliance_id', $context->alliance->id)
                ->where('subscription_id', $subscription->id)
                ->whereKey($candidateId)
                ->firstOrFail();
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->whereKey($route->batch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $candidate = KingdomIngestionCandidate::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('subscription_id', $subscription->id)
                ->where('batch_id', $batch->id)
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($candidate->state === KingdomIngestionCandidateState::Rejected) {
                return $candidate;
            }
            if ($candidate->state !== KingdomIngestionCandidateState::Quarantined) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only quarantined ingestion candidates can be rejected.',
                ]);
            }

            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Rejected,
                'rejection_code' => 'manager_rejected',
            ])->save();
            $batch->increment('records_rejected');

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'quarantine_code' => $candidate->quarantine_code,
                'rejection_code' => 'manager_rejected',
                'origin' => 'player',
            ];
            $event = 'kingdoms.ingestion_candidate_rejected';
            $this->audit->record($event, $context->actor, $candidate, $context->alliance, $metadata);
            $this->outbox->record($event, (string) $context->alliance->id, $candidate, $metadata);

            return $candidate->refresh();
        });
    }
}
