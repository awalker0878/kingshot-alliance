<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomIngestionCandidateState;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Enums\KingdomIngestionTargetKind;
use App\Domain\Kingdoms\Models\KingdomIngestionBatch;
use App\Domain\Kingdoms\Models\KingdomIngestionCandidate;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class ReplayKingdomIngestionCandidate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIngestionAdapterRegistry $adapters,
        private PromoteKingdomIngestionPlayerSnapshot $promotePlayer,
        private PromoteKingdomIngestionAllianceObservation $promoteAlliance,
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

            if ($candidate->state !== KingdomIngestionCandidateState::Quarantined) {
                throw ValidationException::withMessages([
                    'candidate' => 'Only quarantined ingestion candidates can be replayed.',
                ]);
            }

            if ($subscription->state !== KingdomIngestionSubscriptionState::Active) {
                throw ValidationException::withMessages([
                    'subscription' => 'The ingestion subscription must be active before replay.',
                ]);
            }

            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            if ($lockedAlliance->kingdom_id === null || $lockedAlliance->kingdom_id !== $subscription->kingdom_id) {
                throw ValidationException::withMessages([
                    'subscription' => 'Replay is blocked because the alliance Kingdom no longer matches the subscription context.',
                ]);
            }

            $adapter = $this->adapters->require($subscription->adapter_key);
            if (
                $adapter->version() !== $subscription->adapter_version
                || in_array($candidate->target_kind, $adapter->supportedTargetKinds(), true) === false
            ) {
                throw ValidationException::withMessages([
                    'subscription' => 'Replay is blocked because the candidate source/version is no longer approved.',
                ]);
            }

            $batch = KingdomIngestionBatch::query()
                ->where('subscription_id', $subscription->id)
                ->lockForUpdate()
                ->findOrFail($candidate->batch_id);

            $previousCode = $candidate->quarantine_code;
            $candidate->forceFill([
                'state' => KingdomIngestionCandidateState::Pending,
                'quarantine_code' => null,
                'rejection_code' => null,
            ])->save();
            if ($batch->records_quarantined > 0) {
                $batch->decrement('records_quarantined');
            }

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'batch_id' => (string) $batch->id,
                'candidate_id' => (string) $candidate->id,
                'target_kind' => $candidate->target_kind->value,
                'previous_quarantine_code' => $previousCode,
            ];
            $event = 'kingdoms.ingestion_replay_requested';
            $this->audit->record($event, $actor, $candidate, $lockedAlliance, $metadata);
            $this->outbox->record($event, (string) $lockedAlliance->id, $candidate, $metadata);

            match ($candidate->target_kind) {
                KingdomIngestionTargetKind::PlayerSnapshot => $this->promotePlayer->handle(
                    (string) $subscription->id,
                    (string) $candidate->id,
                ),
                KingdomIngestionTargetKind::AllianceObservation => $this->promoteAlliance->handle(
                    (string) $subscription->id,
                    (string) $candidate->id,
                ),
            };

            return $candidate->refresh();
        });
    }
}
