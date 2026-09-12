<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\AnnouncementBroadcastSource;
use App\Contexts\Alliance\Content\Services\NextBroadcastOccurrence;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Queries\AllianceMemberAudienceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;

/** Create at most the requested occurrences, never their complete recipient lists. */
final readonly class MaterializeAnnouncementBroadcastRuns
{
    public function __construct(
        private AllianceMemberAudienceQuery $audience,
        private NextBroadcastOccurrence $nextOccurrence,
        private AnnouncementBroadcastSource $source,
        private KingdomReferenceQuery $kingdoms,
    ) {}

    public function handle(int $limit): int
    {
        $once = ContentItem::query()->selectRaw("'once' as kind, id, alliance_id, id as content_item_id, published_at as due_at")
            ->where('type', ContentType::Announcement->value)->where('status', ContentStatus::Published->value)
            ->where('notify_members', true)->whereNull('archived_at')->whereNull('broadcasted_at')
            ->whereNotNull('published_at')->where('published_at', '<=', now())->toBase();
        $recurring = AnnouncementBroadcastSchedule::query()
            ->selectRaw("'recurring' as kind, id, alliance_id, content_item_id, CASE WHEN last_materialized_at > next_run_at THEN last_materialized_at ELSE next_run_at END as due_at")
            ->where('status', BroadcastScheduleStatus::Active->value)
            ->whereNotNull('next_run_at')->where('next_run_at', '<=', now())->toBase();
        $sources = DB::query()->fromSub($once->unionAll($recurring), 'due')
            ->orderBy('due_at')->orderBy('kind')->orderBy('id')->limit(max(1, min(100, $limit)))->get();
        foreach ($sources as $candidate) {
            $this->materialize((string) $candidate->alliance_id, (string) $candidate->content_item_id,
                $candidate->kind === 'recurring' ? (string) $candidate->id : null);
        }

        return $sources->count();
    }

    private function materialize(string $allianceId, string $itemId, ?string $scheduleId): void
    {
        DB::transaction(function () use ($allianceId, $itemId, $scheduleId): void {
            $alliance = Alliance::query()->whereKey($allianceId)->sharedLock()->first();
            if (! $alliance instanceof Alliance) {
                return;
            }
            $active = $alliance->status === AllianceStatus::Active;
            try {
                $this->kingdoms->lockActiveShared((string) $alliance->kingdom_id);
            } catch (ModelNotFoundException) {
                $active = false;
            }
            // Same owner lock order as content edits: item before schedule, then run.
            $item = ContentItem::query()->whereKey($itemId)->where('alliance_id', $allianceId)->lockForUpdate()->first();
            if (! $item instanceof ContentItem) {
                return;
            }
            $schedule = $scheduleId === null ? null : AnnouncementBroadcastSchedule::query()
                ->whereKey($scheduleId)->where('alliance_id', $allianceId)->where('content_item_id', $itemId)->lockForUpdate()->first();
            if ($scheduleId !== null && (! $schedule instanceof AnnouncementBroadcastSchedule
                || $schedule->status !== BroadcastScheduleStatus::Active || $schedule->next_run_at === null
                || $schedule->next_run_at->isFuture())) {
                return;
            }
            if ($scheduleId === null && ($item->broadcasted_at !== null || ! $this->source->published($item))) {
                return;
            }
            if ($schedule !== null && (! $active || ! $this->source->published($item)
                || $schedule->ends_at?->isPast() === true || $schedule->weekdays === [])) {
                $schedule->forceFill(['status' => BroadcastScheduleStatus::Completed, 'next_run_at' => null])->save();

                return;
            }
            $scheduledFor = $schedule->next_run_at ?? CarbonImmutable::instance($item->published_at ?? now());
            $key = hash('sha256', implode('|', ['announcement', $allianceId, $itemId,
                $item->current_revision_number, $scheduleId ?? 'once', $schedule->generation ?? 0,
                $scheduleId === null ? 'publication' : $scheduledFor->toIso8601String()]));
            AnnouncementBroadcastRun::query()->firstOrCreate(['idempotency_key' => $key], [
                'alliance_id' => $allianceId, 'content_item_id' => $itemId, 'schedule_id' => $scheduleId,
                'content_revision_number' => $item->current_revision_number, 'schedule_generation' => $schedule?->generation,
                'scheduled_for' => $scheduledFor, 'recipient_upper_bound' => $active ? $this->audience->upperBound($allianceId) : null,
                'last_visited_at' => now(), 'status' => $active ? BroadcastRunStatus::Pending : BroadcastRunStatus::Cancelled,
                'cancelled_at' => $active ? null : now(), 'cancellation_reason' => $active ? null : 'inactive-scope',
            ]);
            if ($schedule === null) {
                // Durable occurrence creation is distinct from its recipient-completion timestamp.
                $item->forceFill(['broadcasted_at' => now()])->save();
            } else {
                $next = $this->nextOccurrence->calculate(array_values($schedule->weekdays), $schedule->local_time,
                    $schedule->timezone, $scheduledFor, $schedule->ends_at);
                $schedule->forceFill(['status' => $next === null ? BroadcastScheduleStatus::Completed : BroadcastScheduleStatus::Active,
                    'last_run_at' => $scheduledFor, 'next_run_at' => $next,
                    'last_materialized_at' => max(CarbonImmutable::now('UTC'), $schedule->last_materialized_at ?? CarbonImmutable::now('UTC'))->addMicrosecond(),
                ])->save();
            }
        });
    }
}
