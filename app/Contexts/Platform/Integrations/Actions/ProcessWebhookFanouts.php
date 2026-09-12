<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookFanout;
use App\Contexts\Platform\Integrations\Models\WebhookSubscription;
use Illuminate\Support\Facades\DB;

final class ProcessWebhookFanouts
{
    public function handle(int $limit = 100): int
    {
        $remaining = max(1, min(500, $limit));
        $queued = 0;
        // Source visits consume budget even when a source has no recipients.
        while ($remaining > 0) {
            $id = WebhookFanout::query()->whereNull('completed_at')
                ->orderByRaw('visited_at ASC NULLS FIRST')->orderBy('id')->value('id');
            if (! is_string($id)) {
                break;
            }
            $pageSize = min(25, $remaining);
            $queued += $this->page($id, $pageSize);
            $remaining -= $pageSize;
        }

        return $queued;
    }

    public function page(string $fanoutId, int $limit): int
    {
        return DB::transaction(function () use ($fanoutId, $limit): int {
            $fanout = WebhookFanout::query()->whereKey($fanoutId)->lock('for update skip locked')->first();
            if (! $fanout instanceof WebhookFanout || $fanout->completed_at !== null) {
                return 0;
            }
            $subscriptions = WebhookSubscription::query()
                ->when($fanout->alliance_id !== null, fn ($query) => $query->where('alliance_id', $fanout->alliance_id))
                ->where('id', '<=', $fanout->upper_subscription_id ?? '')
                ->when($fanout->after_subscription_id !== null, fn ($query) => $query->where('id', '>', $fanout->after_subscription_id))
                ->orderBy('id')->limit(max(1, min(25, $limit)))->lockForUpdate()->get();
            $queued = 0;
            foreach ($subscriptions as $subscription) {
                if ($subscription->receives((string) $fanout->event_type)) {
                    $payload = [
                        'schema_version' => '1.0',
                        'id' => (string) $fanout->source_message_id,
                        'event' => (string) $fanout->event_type,
                        'occurred_at' => (string) $fanout->occurred_at,
                        'alliance_id' => (string) $subscription->alliance_id,
                        'data' => $fanout->payload,
                    ];
                    $oversized = $fanout->payload_oversized || strlen(json_encode($payload, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES)) > 262144;
                    $delivery = WebhookDelivery::query()->firstOrCreate([
                        'idempotency_key' => 'webhook:'.$subscription->id.':'.$fanout->source_message_id,
                    ], [
                        'alliance_id' => $subscription->alliance_id,
                        'webhook_subscription_id' => $subscription->id,
                        'source_message_id' => $fanout->source_message_id,
                        'event_type' => $fanout->event_type,
                        'payload' => $oversized ? null : $payload,
                        'status' => $oversized ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
                        'available_at' => now(),
                        'last_error' => $oversized ? 'Webhook payload exceeded the 256 KiB delivery limit.' : null,
                    ]);
                    if ($delivery->wasRecentlyCreated && ! $oversized) {
                        DeliverWebhookJob::dispatch((string) $delivery->id)->onQueue('integrations')->afterCommit();
                        $queued++;
                    }
                }
                $fanout->after_subscription_id = (string) $subscription->id;
            }
            $fanout->forceFill([
                'visited_at' => now(),
                'completed_at' => $subscriptions->count() < max(1, min(25, $limit)) ? now() : null,
            ])->save();

            if ($fanout->completed_at !== null) {
                $fanout->forceFill(['payload' => null])->save();
            }

            return $queued;
        });
    }
}
