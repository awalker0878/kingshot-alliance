<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Models\KingdomIngestionSubscription;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionKingdomIngestionSubscription
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private KingdomIngestionAdapterRegistry $adapters,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $subscriptionId,
        KingdomIngestionSubscriptionState $target,
    ): KingdomIngestionSubscription {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $subscriptionId, $target): KingdomIngestionSubscription {
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->findOrFail($subscriptionId);

            if ($subscription->state === $target) {
                return $subscription->load('kingdom');
            }

            $nextRunAt = $subscription->next_run_at;
            if ($target === KingdomIngestionSubscriptionState::Active) {
                $lockedAlliance = Alliance::query()->lockForUpdate()->findOrFail($alliance->id);
                if ($lockedAlliance->kingdom_id === null || $lockedAlliance->kingdom_id !== $subscription->kingdom_id) {
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

            $event = 'kingdoms.ingestion_subscription_state_changed';
            $this->audit->record($event, $actor, $subscription, $alliance, $metadata);
            $this->outbox->record($event, (string) $alliance->id, $subscription, $metadata);

            return $subscription->refresh()->load('kingdom');
        });
    }
}
