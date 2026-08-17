<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Ingestion\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionSubscriptionState;
use App\Contexts\Intelligence\Ingestion\Models\KingdomIngestionSubscription;
use App\Contexts\Intelligence\Ingestion\Services\KingdomIngestionAdapterRegistry;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionKingdomIngestionSubscription
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomIngestionAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $subscriptionId,
        KingdomIngestionSubscriptionState $target,
    ): string {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $subscriptionId, $target): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->state === $target) {
                return (string) $subscription->id;
            }

            $nextRunAt = $subscription->next_run_at;
            if ($target === KingdomIngestionSubscriptionState::Active) {
                if ($scope->kingdomId === null
                    || (string) $scope->kingdomId !== (string) $subscription->kingdom_id) {
                    throw ValidationException::withMessages([
                        'state' => 'A subscription can only be activated for the alliance current Kingdom.',
                    ]);
                }

                $adapter = $this->adapters->require($subscription->adapter_key);
                if ($adapter->version() !== $subscription->adapter_version) {
                    throw ValidationException::withMessages([
                        'state' => 'The configured source adapter version is no longer approved for activation.',
                    ]);
                }

                $nextRunAt = $adapter instanceof KingdomIngestionAcquisitionAdapter ? now() : null;
            }

            $from = $subscription->state;
            $subscription->forceFill([
                'state' => $target,
                'next_run_at' => $target === KingdomIngestionSubscriptionState::Active ? $nextRunAt : null,
                'circuit_open_until' => $target === KingdomIngestionSubscriptionState::Active ? null : $subscription->circuit_open_until,
                'blocked_at' => $target === KingdomIngestionSubscriptionState::Active ? null : $subscription->blocked_at,
                'blocked_reason' => $target === KingdomIngestionSubscriptionState::Active ? null : $subscription->blocked_reason,
                'last_failure_code' => $target === KingdomIngestionSubscriptionState::Active ? null : $subscription->last_failure_code,
                'consecutive_failures' => $target === KingdomIngestionSubscriptionState::Active ? 0 : $subscription->consecutive_failures,
            ])->save();

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'kingdom_id' => (string) $subscription->kingdom_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'from_state' => $from->value,
                'to_state' => $target->value,
            ];

            $event = 'intelligence.ingestion_subscription_state_changed';
            $this->audit->record($event, $actor, $subscription, $allianceId, $metadata);
            $this->outbox->record($event, (string) $allianceId, $subscription, $metadata);

            return (string) $subscription->id;
        });
    }
}
