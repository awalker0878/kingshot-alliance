<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Kingdoms\Contracts\KingdomIngestionAcquisitionAdapter;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use App\Domain\Kingdoms\Services\KingdomIngestionAdapterRegistry;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class TransitionKingdomIngestionSubscription
{
    public function __construct(
        private AllianceMutationAuthority $authority,
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
        return DB::transaction(function () use ($alliance, $actor, $subscriptionId, $target): KingdomIngestionSubscription {
            $context = $this->authority->require($actor, $alliance, PermissionKey::KingdomManage);
            $subscription = KingdomIngestionSubscription::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($subscriptionId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($subscription->state === $target) {
                return $subscription->load('kingdom');
            }

            $nextRunAt = $subscription->next_run_at;
            if ($target === KingdomIngestionSubscriptionState::Active) {
                if ($context->alliance->kingdom_id === null
                    || (string) $context->alliance->kingdom_id !== (string) $subscription->kingdom_id) {
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
            $this->audit->record($event, $context->actor, $subscription, $context->alliance, $metadata);
            $this->outbox->record($event, (string) $context->alliance->id, $subscription, $metadata);

            return $subscription->refresh()->load('kingdom');
        });
    }
}
