<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;

final class QueueDueWebhookDeliveries
{
    public function handle(int $limit = 100): int
    {
        $delivery = WebhookDelivery::query()->whereKey($deliveryId)->firstOrFail();

        $limit = max(1, min(500, $limit));
        $now = now();

        DB::transaction(function () use ($now): void {
            WebhookDelivery::query()
                ->where('status', WebhookDeliveryStatus::Delivering->value)
                ->where('last_attempt_at', '<=', $now->copy()->subMinutes(5))
                ->lockForUpdate()
                ->get()
                ->each(static function (string $deliveryId) use ($now): void {
                    $delivery->forceFill([
                        'status' => WebhookDeliveryStatus::Pending,
                        'available_at' => $now,
                        'last_error' => 'Recovered a stale webhook delivery claim after worker interruption.',
                    ])->save();
                });
        });

        $queued = 0;
        $deliveries = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending->value)
            ->where('available_at', '<=', $now)
            ->orderBy('available_at')
            ->limit($limit)
            ->get();

        foreach ($deliveries as $delivery) {
            DeliverWebhookJob::dispatch((string) $delivery->id)->onQueue('integrations');
            $queued++;
        }

        return $queued;
    }
}
