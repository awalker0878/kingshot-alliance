<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Actions;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Jobs\DeliverWebhookJob;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

final class QueueDueWebhookDeliveries
{
    public function handle(int $limit = 100): int
    {
        $limit = max(1, min(500, $limit));
        $now = now();

        $recoveryLimit = $limit;
        DB::transaction(function () use ($now, $recoveryLimit): void {
            WebhookDelivery::query()
                ->where(static function ($stale) use ($now): void {
                    $stale->where(static function ($delivering) use ($now): void {
                        $delivering->where('status', WebhookDeliveryStatus::Delivering->value)
                            ->where('last_attempt_at', '<=', $now->copy()->subMinutes(5));
                    })->orWhere(static function ($queued) use ($now): void {
                        $queued->where('status', WebhookDeliveryStatus::Queued->value)
                            ->where('updated_at', '<=', $now->copy()->subMinutes(5));
                    });
                })
                ->orderBy('updated_at')
                ->orderBy('id')
                ->limit($recoveryLimit)
                ->lock('for update skip locked')
                ->get()
                ->each(static function (WebhookDelivery $delivery) use ($now): void {
                    $delivery->forceFill([
                        'status' => $delivery->attempts >= $delivery->max_attempts ? WebhookDeliveryStatus::Failed : WebhookDeliveryStatus::Pending,
                        'available_at' => $now,
                        'attempt_token' => null,
                        'last_error' => $delivery->attempts >= $delivery->max_attempts
                            ? 'Webhook attempt budget exhausted after interruption; provider acknowledgement is unknown.'
                            : 'Recovered a stale webhook delivery claim after worker interruption.',
                    ])->save();
                });
        });

        $deliveryIds = DB::transaction(function () use ($now, $limit): array {
            $deliveries = WebhookDelivery::query()
                ->where('status', WebhookDeliveryStatus::Pending->value)
                ->where('available_at', '<=', $now)
                ->orderBy('available_at')
                ->orderBy('id')
                ->limit($limit)
                ->lock('for update skip locked')
                ->get();

            foreach ($deliveries as $delivery) {
                $delivery->forceFill([
                    'status' => WebhookDeliveryStatus::Queued,
                    'attempt_token' => (string) Str::uuid(),
                ])->save();
            }

            return $deliveries->map(static fn (WebhookDelivery $delivery): array => [
                (string) $delivery->id, (string) $delivery->attempt_token,
            ])->all();
        });

        foreach ($deliveryIds as [$deliveryId, $token]) {
            DeliverWebhookJob::dispatch($deliveryId, $token)->onQueue('integrations');
        }

        return count($deliveryIds);
    }
}
