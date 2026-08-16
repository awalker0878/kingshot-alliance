<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Jobs;

use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

final class DeliverWebhookJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [60, 300, 1800, 7200];

    public int $uniqueFor = 86400;

    public function __construct(public readonly string $deliveryId) {}

    public function uniqueId(): string
    {
        return $this->deliveryId;
    }

    public function handle(DeliverWebhook $deliver): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if ($delivery instanceof WebhookDelivery) {
            $deliver->handle($delivery);
        }
    }

    public function failed(?Throwable $exception): void
    {
        $delivery = WebhookDelivery::query()->find($this->deliveryId);
        if (! $delivery instanceof WebhookDelivery || $delivery->status === WebhookDeliveryStatus::Delivered) {
            return;
        }

        $delivery->forceFill([
            'status' => WebhookDeliveryStatus::Failed,
            'last_error' => mb_substr($exception?->getMessage() ?? 'Webhook delivery exhausted its retry budget.', 0, 1000),
        ])->save();
    }
}
