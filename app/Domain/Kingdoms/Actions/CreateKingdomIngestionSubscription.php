<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomIngestionSubscription
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIngestionAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Alliance $alliance, User $actor, string $adapterKey): KingdomIngestionSubscription
    {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        $adapter = $this->adapters->require(trim($adapterKey));

        return DB::transaction(function () use ($alliance, $actor, $adapter): KingdomIngestionSubscription {
            $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
            if ($lockedAlliance->kingdom_id === null) {
                throw ValidationException::withMessages([
                    'adapter_key' => 'The alliance must have a current Kingdom before automated ingestion can be configured.',
                ]);
            }

            $existing = KingdomIngestionSubscription::query()
                ->where('alliance_id', $lockedAlliance->id)
                ->where('kingdom_id', $lockedAlliance->kingdom_id)
                ->where('adapter_key', $adapter->key())
                ->lockForUpdate()
                ->first();

            if ($existing instanceof KingdomIngestionSubscription) {
                throw ValidationException::withMessages([
                    'adapter_key' => 'That source adapter is already configured for the current Kingdom.',
                ]);
            }

            $subscription = KingdomIngestionSubscription::query()->create([
                'alliance_id' => $lockedAlliance->id,
                'kingdom_id' => $lockedAlliance->kingdom_id,
                'adapter_key' => $adapter->key(),
                'adapter_version' => $adapter->version(),
                'state' => KingdomIngestionSubscriptionState::Active,
                'next_run_at' => $adapter instanceof KingdomIngestionAcquisitionAdapter ? now() : null,
            ]);

            $metadata = [
                'subscription_id' => (string) $subscription->id,
                'kingdom_id' => (string) $subscription->kingdom_id,
                'adapter_key' => $subscription->adapter_key,
                'adapter_version' => $subscription->adapter_version,
                'state' => $subscription->state->value,
                'acquisition_enabled' => $adapter instanceof KingdomIngestionAcquisitionAdapter,
            ];

            $event = 'kingdoms.ingestion_subscription_created';
            $this->audit->record($event, $actor, $subscription, $lockedAlliance, $metadata);
            $this->outbox->record($event, (string) $lockedAlliance->id, $subscription, $metadata);

            return $subscription->refresh()->load('kingdom');
        });
    }
}
