<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Jobs;

use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Exceptions\WebhookAttemptFailed;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;
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
            $deliver->handle((string) $delivery->id);
        }
    }

    public function failed(?Throwable $exception): void
    {
        if (! $exception instanceof WebhookAttemptFailed || $exception->deliveryId !== $this->deliveryId) {
            return;
        }

        DB::transaction(function () use ($exception): void {
            $delivery = WebhookDelivery::query()->lockForUpdate()->find($this->deliveryId);
            if (! $delivery instanceof WebhookDelivery
                || ! in_array($delivery->status, [WebhookDeliveryStatus::Pending, WebhookDeliveryStatus::Delivering], true)
                || ! hash_equals((string) $delivery->attempt_token, $exception->attemptToken)) {
                return;
            }

            $delivery->forceFill([
                'status' => WebhookDeliveryStatus::Failed,
                'attempt_token' => null,
                'available_at' => now(),
                'last_error' => mb_substr($exception->getMessage(), 0, 1000),
            ])->save();
        });
    }
}
