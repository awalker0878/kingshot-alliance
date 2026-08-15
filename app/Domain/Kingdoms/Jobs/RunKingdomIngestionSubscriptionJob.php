<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Jobs;

use App\Domain\Kingdoms\Actions\CompleteKingdomIngestionBatch;
use App\Domain\Kingdoms\Actions\RunKingdomIngestionSubscription;
use App\Domain\Kingdoms\Enums\KingdomIngestionBatchState;
use App\Contexts\GameWorld\Models\KingdomIngestionBatch;
use App\Contexts\GameWorld\Models\KingdomIngestionSubscription;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Throwable;

final class RunKingdomIngestionSubscriptionJob implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 5;

    public int $timeout = 120;

    public int $uniqueFor = 3600;

    /** @var list<int> */
    public array $backoff = [60, 300, 900, 3600];

    public function __construct(public readonly string $subscriptionId) {}

    public function uniqueId(): string
    {
        return $this->subscriptionId;
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [
            (new WithoutOverlapping('kingdom-ingestion:'.$this->subscriptionId))
                ->releaseAfter(60)
                ->expireAfter(180),
        ];
    }

    public function handle(RunKingdomIngestionSubscription $run): void
    {
        $run->handle($this->subscriptionId);
    }

    public function failed(?Throwable $exception): void
    {
        $subscription = KingdomIngestionSubscription::query()->find($this->subscriptionId);
        if (! $subscription instanceof KingdomIngestionSubscription) {
            return;
        }

        $pending = KingdomIngestionBatch::query()
            ->where('subscription_id', $subscription->id)
            ->where('state', KingdomIngestionBatchState::Pending->value)
            ->latest('started_at')
            ->first();
        if (! $pending instanceof KingdomIngestionBatch) {
            return;
        }

        app(CompleteKingdomIngestionBatch::class)->handle(
            (string) $subscription->id,
            (string) $pending->id,
            KingdomIngestionBatchState::Failed,
            'retry_exhausted',
        );
    }
}
