<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Jobs;

use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class DeliverWebhookJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 30;

    public function __construct(
        public readonly string $deliveryId,
        public readonly ?string $reservationToken = null,
    ) {}

    public function handle(DeliverWebhook $deliver): void
    {
        $deliver->handle($this->deliveryId, $this->reservationToken);
    }
}
