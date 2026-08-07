<?php

declare(strict_types=1);

namespace App\Domain\Content\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Models\ContentItem;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class PublishScheduledContent
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(int $limit = 100): int
    {
        $published = 0;
        $limit = max(1, min($limit, 500));

        $ids = ContentItem::query()
            ->where('status', ContentStatus::Scheduled->value)
            ->whereNotNull('scheduled_for')
            ->where('scheduled_for', '<=', now())
            ->orderBy('scheduled_for')
            ->limit($limit)
            ->pluck('id');

        foreach ($ids as $id) {
            $didPublish = DB::transaction(function () use ($id): bool {
                $item = ContentItem::query()
                    ->where('id', $id)
                    ->where('status', ContentStatus::Scheduled->value)
                    ->whereNotNull('scheduled_for')
                    ->where('scheduled_for', '<=', now())
                    ->lockForUpdate()
                    ->first();

                if (! $item instanceof ContentItem) {
                    return false;
                }

                $alliance = Alliance::query()->findOrFail($item->alliance_id);
                $item->forceFill([
                    'status' => ContentStatus::Published,
                    'published_at' => now(),
                    'scheduled_for' => null,
                    'archived_at' => null,
                ])->save();

                $this->audit->record('content.published_scheduled', null, $item, $alliance, [
                    'revision_number' => $item->current_revision_number,
                ]);
                $this->outbox->record('content.published_scheduled', (string) $alliance->id, $item, [
                    'content_item_id' => $item->id,
                    'revision_number' => $item->current_revision_number,
                ]);

                return true;
            });

            if ($didPublish) {
                $published++;
            }
        }

        return $published;
    }
}
