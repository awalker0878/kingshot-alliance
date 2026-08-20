<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteAuthorization;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryWebhookDelivery
{
    public function __construct(
        private AllianceWriteAuthorization $allianceAuthority,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $deliveryId): string
    {
        $deliveryId = DB::transaction(function () use ($allianceId, $actorPlayerId, $deliveryId): string {
            [$currentAlliance, $currentActor] = $this->allianceAuthority->authorizeManagerActive($actorPlayerId, $allianceId);
            $delivery = WebhookDelivery::query()
                ->where('alliance_id', $currentAlliance->allianceId)
                ->lockForUpdate()
                ->findOrFail($deliveryId);

            if ($delivery->status !== WebhookDeliveryStatus::Failed) {
                throw ValidationException::withMessages(['delivery' => 'Only exhausted webhook deliveries can be retried manually.']);
            }
            if (! is_array($delivery->payload)) {
                throw ValidationException::withMessages(['delivery' => 'This delivery has no payload to retry.']);
            }

            $subscription = WebhookSubscription::query()
                ->where('alliance_id', $currentAlliance->allianceId)
                ->lockForUpdate()
                ->find($delivery->webhook_subscription_id);
            if (! $subscription instanceof WebhookSubscription || ! $subscription->is_active || $subscription->revoked_at !== null) {
                throw ValidationException::withMessages(['delivery' => 'Reactivate or replace the webhook subscription before retrying.']);
            }

            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Pending,
                'available_at' => now(),
                'last_error' => 'Manual retry requested.',
            ])->save();

            $this->audit->record('integration.webhook_delivery.retry_queued', $currentActor, $delivery, $currentAlliance->allianceId, [
                'subscription_id' => $subscription->id,
                'attempts' => $delivery->attempts,
            ]);

            return (string) $delivery->id;
        });

        DeliverWebhookJob::dispatch($deliveryId)->onQueue('integrations');

        return $deliveryId;
    }
}
