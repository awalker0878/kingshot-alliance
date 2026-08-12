<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RejectKingdomIngestionCandidate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $subscriptionId,
        string $candidateId,
    ): KingdomIngestionCandidate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $subscriptionId, $candidateId): KingdomIngestionCandidate {
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($subscriptionId);
            $candidate = KingdomIngestionCandidate::query()
                ->where('alliance_id', $alliance->id)
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($candidateId);

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
            $candidate->batch()->increment('records_rejected');

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $candidate->batch_id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'quarantine_code' => $candidate->quarantine_code,
                'rejection_code' => 'manager_rejected',
            ];

            $event = 'kingdoms.ingestion_candidate_rejected';
            $this->audit->record($event, $actor, $candidate, $alliance, $metadata);
            $this->outbox->record($event, (string) $alliance->id, $candidate, $metadata);

            return $candidate->refresh();
        });
    }
}
