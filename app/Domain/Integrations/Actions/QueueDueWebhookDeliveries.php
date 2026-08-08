<?php

declare(strict_types=1);

namespace App\Domain\Integrations\Actions;

use App\Domain\Integrations\Enums\WebhookDeliveryStatus;
use App\Domain\Integrations\Jobs\DeliverWebhookJob;
use App\Domain\Integrations\Models\WebhookDelivery;

final class QueueDueWebhookDeliveries
{
    public function handle(int $limit = 100): int
    {
        $queued = 0;
        $deliveries = WebhookDelivery::query()
            ->where('status', WebhookDeliveryStatus::Pending->value)
            ->where('available_at', '<=', now())
            ->orderBy('available_at')
            ->limit(max(1, min(500, $limit)))
            ->get();

        foreach ($deliveries as $delivery) {
            DeliverWebhookJob::dispatch((string) $delivery->id)->onQueue('integrations');
            $queued++;
        }

        return $queued;
    }
}
