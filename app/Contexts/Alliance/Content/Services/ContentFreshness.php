<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Services;

use App\Contexts\Alliance\Content\Enums\ContentFreshnessStatus;
use App\Contexts\Alliance\Content\Models\ContentItem;
use Carbon\CarbonImmutable;
use Illuminate\Support\Carbon;

final class ContentFreshness
{
    /**
     * @return array{status:string,dueAt:string|null,daysUntilDue:int|null}
     */
    public function assess(ContentItem $item, ?CarbonImmutable $today = null): array
    {
        if (! $item->type->requiresProvenance()) {
            return $this->result(ContentFreshnessStatus::NotApplicable, null, null);
        }

        if (! $item->reviewed_at instanceof Carbon) {
            return $this->result(ContentFreshnessStatus::Stale, null, null);
        }

        $today ??= CarbonImmutable::today('UTC');
        $maximumAge = max(1, (int) config('content.knowledge_review_max_age_days', 90));
        $warningWindow = min(
            $maximumAge,
            max(0, (int) config('content.knowledge_review_warning_days', 14)),
        );
        $dueAt = CarbonImmutable::instance($item->reviewed_at)->startOfDay()->addDays($maximumAge);
        $daysUntilDue = (int) $today->diffInDays($dueAt, false);
        $status = match (true) {
            $daysUntilDue < 0 => ContentFreshnessStatus::Stale,
            $daysUntilDue <= $warningWindow => ContentFreshnessStatus::DueSoon,
            default => ContentFreshnessStatus::Current,
        };

        return $this->result($status, $dueAt->toDateString(), $daysUntilDue);
    }

    /** @return array{status:string,dueAt:string|null,daysUntilDue:int|null} */
    private function result(ContentFreshnessStatus $status, ?string $dueAt, ?int $daysUntilDue): array
    {
        return [
            'status' => $status->value,
            'dueAt' => $dueAt,
            'daysUntilDue' => $daysUntilDue,
        ];
    }
}
