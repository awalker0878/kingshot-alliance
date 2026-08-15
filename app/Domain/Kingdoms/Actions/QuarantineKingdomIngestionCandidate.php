<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Contexts\GameWorld\Models\KingdomIngestionBatch;
use App\Contexts\GameWorld\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Services\KingdomIngestionMutationState;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class QuarantineKingdomIngestionCandidate
{
    public function __construct(
        private KingdomIngestionMutationState $mutations,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $subscriptionId, string $candidateId, string $reasonCode): KingdomIngestionCandidate
    {
        $reasonCode = trim($reasonCode);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $reasonCode) !== 1) {
            throw ValidationException::withMessages([
                'reason' => 'The quarantine reason must be a stable lowercase code.',
            ]);
        }

        return DB::transaction(function () use ($subscriptionId, $candidateId, $reasonCode): KingdomIngestionCandidate {
            $context = $this->mutations->lockSubscription($subscriptionId);
            $route = KingdomIngestionCandidate::query()
                ->select(['id', 'batch_id'])
                ->where('subscription_id', $context->subscription->id)
                ->whereKey($candidateId)
                ->firstOrFail();
            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $context->subscription->id)
                ->whereKey($route->batch_id)
                ->lockForUpdate()
                ->firstOrFail();
            $candidate = KingdomIngestionCandidate::query()
                ->where('subscription_id', $context->subscription->id)
                ->where('batch_id', $batch->id)
                ->whereKey($route->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($candidate->state === KingdomIngestionCandidateState::Quarantined) {
                if ($candidate->quarantine_code !== $reasonCode) {
                    throw ValidationException::withMessages([
                        'reason' => 'An already-quarantined candidate cannot be relabelled in place.',
                    ]);
                }
                return $candidate;
            }
            if ($candidate->state !== KingdomIngestionCandidateState::Pending) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only pending ingestion candidates can be quarantined.',
                ]);
            }

            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Quarantined,
                'quarantine_code' => $reasonCode,
            ])->save();
            $batch->increment('records_quarantined');

            $event = 'kingdoms.ingestion_candidate_quarantined';
            $this->outbox->record(
                $event,
                (string) $context->alliance->id,
                $candidate,
                [
                    'subscription_id' => (string) $context->subscription->id,
                    'batch_id' => (string) $batch->id,
                    'candidate_id' => (string) $candidate->id,
                    'target_kind' => $candidate->target_kind->value,
                    'quarantine_code' => $reasonCode,
                    'origin' => 'system',
                ],
                $event.':'.$candidate->id.':'.$reasonCode,
            );

            return $candidate->refresh();
        });
    }
}
