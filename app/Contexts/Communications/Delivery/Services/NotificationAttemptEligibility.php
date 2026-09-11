<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** One readiness predicate for candidate selection and the subsequent locked read. */
final class NotificationAttemptEligibility
{
    public const int LEASE_SECONDS = 300;

    public const string EXHAUSTED_REASON = 'Attempt budget exhausted without a recorded delivery acknowledgement; provider outcome may be unknown.';

    /**
     * Exhausted rows remain actionable once for terminal reconciliation, not a new send.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return Builder<TModel>
     */
    public function constrain(Builder $query, CarbonImmutable $now): Builder
    {
        return $query->where('due_at', '<=', $now)
            ->where(static function (Builder $query) use ($now): void {
                $query->where('status', DeliveryStatus::Queued->value)
                    ->orWhere(static function (Builder $retry) use ($now): void {
                        $retry->where('status', DeliveryStatus::Failed->value)
                            ->whereNotNull('next_attempt_at')
                            ->where('next_attempt_at', '<=', $now);
                    })->orWhere(static function (Builder $stale) use ($now): void {
                        $stale->where('status', DeliveryStatus::Pending->value)
                            ->where('updated_at', '<=', $now->subSeconds(self::LEASE_SECONDS));
                    });
            });
    }
}
