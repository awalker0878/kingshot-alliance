<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomIngestionSubscription
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private KingdomIngestionAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, Player $actor, string $adapterKey): KingdomIngestionSubscription
    {
        $adapter = $this->adapters->require(trim($adapterKey));

        return DB::transaction(function () use ($alliance, $actor, $adapter): KingdomIngestionSubscription {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);
            if ($context->alliance->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'adapter_key' => 'The alliance must have a current Kingdom before automated ingestion can be configured.',
                ]);
            }

            $existing = KingdomIngestionSubscription::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('kingdom_id', $context->alliance->kingdom_id)
                ->where('adapter_key', $adapter->key())
                ->first();

            if ($existing instanceof KingdomIngestionSubscription) {
                throw ValidationException::withMessages([
                    'adapter_key' => 'That source adapter is already configured for the current Kingdom.',
                ]);
            }

            try {
                $subscription = KingdomIngestionSubscription::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'kingdom_id' => $context->alliance->kingdom_id,
                    'adapter_key' => $adapter->key(),
                    'adapter_version' => $adapter->version(),
                    'state' => KingdomIngestionSubscriptionState::Active,
                    'next_run_at' => $adapter instanceof KingdomIngestionAcquisitionAdapter ? now() : null,
                ]);
            } catch (QueryException $exception) {
                // The unique (alliance, kingdom, adapter) constraint is the hard race
                // guard when two managers configure the same source concurrently.
                if (KingdomIngestionSubscription::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('kingdom_id', $context->alliance->kingdom_id)
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

            $event = 'kingdoms.ingestion_subscription_created';
            $this->audit->record($event, $context->actor, $subscription, $context->alliance, $metadata);
            $this->outbox->record($event, (string) $context->alliance->id, $subscription, $metadata);

            return $subscription->refresh()->load('kingdom');
        });
    }
}
