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
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomIngestionSubscription
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private KingdomIngestionAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $adapterKey): string
    {
        $adapter = $this->adapters->require(trim($adapterKey));

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $adapter): string {
            [$scope, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            if ($scope->kingdomId === '') {
                throw ValidationException::withMessages([
                    'adapter_key' => 'The alliance must have a current Kingdom before automated ingestion can be configured.',
                ]);
            }

            $existing = KingdomIngestionSubscription::query()
                ->where('alliance_id', $allianceId)
                ->where('kingdom_id', $scope->kingdomId)
                ->where('adapter_key', $adapter->key())
                ->first();

            if ($existing instanceof KingdomIngestionSubscription) {
                throw ValidationException::withMessages([
                    'adapter_key' => 'That source adapter is already configured for the current Kingdom.',
                ]);
            }

            try {
                $subscription = KingdomIngestionSubscription::query()->create([
                    'alliance_id' => $allianceId,
                    'kingdom_id' => $scope->kingdomId,
                    'adapter_key' => $adapter->key(),
                    'adapter_version' => $adapter->version(),
                    'state' => KingdomIngestionSubscriptionState::Active,
                    'next_run_at' => $adapter instanceof KingdomIngestionAcquisitionAdapter ? now() : null,
                ]);
            } catch (QueryException $exception) {
                // The unique (alliance, kingdom, adapter) constraint is the hard race
                // guard when two managers configure the same source concurrently.
                if (KingdomIngestionSubscription::query()
                    ->where('alliance_id', $allianceId)
                    ->where('kingdom_id', $scope->kingdomId)
                    ->where('adapter_key', $adapter->key())
                    ->exists()) {
                    throw ValidationException::withMessages([
                        'adapter_key' => 'That source adapter is already configured for the current Kingdom.',
                    ]);
                }

                throw $exception;
            }

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'kingdom_id' => (string) $subscription->kingdom_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'state' => $subscription->state->value,
                'acquisition_enabled' => $adapter instanceof KingdomIngestionAcquisitionAdapter,
            ];

            $event = 'intelligence.ingestion_subscription_created';
            $this->audit->record($event, $actor, $subscription, $allianceId, $metadata);
            $this->outbox->record($event, (string) $allianceId, $subscription, $metadata);

            return (string) $subscription->id;
        });
    }
}
