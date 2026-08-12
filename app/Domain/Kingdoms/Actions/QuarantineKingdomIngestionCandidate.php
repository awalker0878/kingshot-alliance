<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class QuarantineKingdomIngestionCandidate
{
    public function __construct(private OutboxRecorder $outbox) {}

    public function handle(string $subscriptionId, string $candidateId, string $reasonCode): KingdomIngestionCandidate
    {
        $reasonCode = trim($reasonCode);
        if (preg_match('/^[a-z0-9][a-z0-9._-]{0,79}$/', $reasonCode) !== 1) {
            throw ValidationException::withMessages([
                'reason' => 'The quarantine reason must be a stable lowercase code.',
            ]);
        }

        return DB::transaction(function () use ($subscriptionId, $candidateId, $reasonCode): KingdomIngestionCandidate {
            $subscription = KingdomIngestionSubscription::query()->lockForUpdate()->findOrFail($subscriptionId);
            $candidate = KingdomIngestionCandidate::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($candidateId);

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
            $candidate->batch()->increment('records_quarantined');

            $event = 'kingdoms.ingestion_candidate_quarantined';
            $this->outbox->record(
                $event,
                (string) $candidate->alliance_id,
                $candidate,
                [
                    'subscription_id' => (string) $subscription->id,
                    'batch_id' => (string) $candidate->batch_id,
                    'candidate_id' => (string) $candidate->id,
                    'target_kind' => $candidate->target_kind->value,
                    'quarantine_code' => $reasonCode,
                ],
                $event.':'.$candidate->id.':'.$reasonCode,
            );

            return $candidate->refresh();
        });
    }
}
