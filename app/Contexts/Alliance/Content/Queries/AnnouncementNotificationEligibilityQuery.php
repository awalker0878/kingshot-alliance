<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\AnnouncementBroadcastSource;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final readonly class AnnouncementNotificationEligibilityQuery
{
    public function __construct(private AllianceAuthorization $authorization, private AnnouncementBroadcastSource $broadcastSource, private KingdomReferenceQuery $kingdoms) {}

    public function allows(NotificationSource $source, PlayerReference $player): bool
    {
        $allianceId = $source->metadataString('alliance_id');
        if ($allianceId === null || $this->kingdoms->findActive($player->kingdomId) === null || $source->subjectType !== 'content_item' || $source->subjectId === null
            || $source->metadataString('content_item_id') !== $source->subjectId) {
            return false;
        }
        if (($source->metadata['test_delivery'] ?? false) === true) {
            return $this->authorization->allows($player->playerId, $allianceId, AlliancePermission::ContentManage)
                && ContentItem::query()->whereKey($source->subjectId)->where('alliance_id', $allianceId)
                    ->where('type', ContentType::Announcement->value)
                    ->where('status', '!=', ContentStatus::Archived->value)->whereNull('archived_at')->exists();
        }

        $runId = $source->metadataString('broadcast_run_id');
        if ($runId === null || ! $this->authorization->allows($player->playerId, $allianceId, AlliancePermission::View)) {
            return false;
        }
        $run = AnnouncementBroadcastRun::query()->whereKey($runId)->where('alliance_id', $allianceId)
            ->where('content_item_id', $source->subjectId)->first();
        if (! $run instanceof AnnouncementBroadcastRun) {
            return false;
        }
        $item = ContentItem::query()->whereKey($run->content_item_id)->where('alliance_id', $allianceId)->first();
        $schedule = $run->schedule_id === null ? null : AnnouncementBroadcastSchedule::query()
            ->whereKey($run->schedule_id)->where('alliance_id', $allianceId)->where('content_item_id', $run->content_item_id)->first();

        return $this->broadcastSource->allows($run, $item, $schedule);
    }
}
