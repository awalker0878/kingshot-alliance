<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final readonly class AnnouncementNotificationEligibilityQuery
{
    public function __construct(private AllianceAuthorization $authorization, private ContentQuery $content) {}

    public function allows(NotificationSource $source, PlayerReference $player): bool
    {
        $allianceId = $source->metadataString('alliance_id');
        if ($allianceId === null || $source->subjectType !== 'content_item' || $source->subjectId === null
            || $source->metadataString('content_item_id') !== $source->subjectId) {
            return false;
        }
        if (($source->metadata['test_delivery'] ?? false) === true) {
            return $this->authorization->allows($player->playerId, $allianceId, AlliancePermission::ContentManage)
                && ContentItem::query()->whereKey($source->subjectId)->where('alliance_id', $allianceId)
                    ->where('type', ContentType::Announcement->value)
                    ->where('status', '!=', ContentStatus::Archived->value)->whereNull('archived_at')->exists();
        }

        return $this->authorization->allows($player->playerId, $allianceId, AlliancePermission::View)
            && $this->content->publishedAnnouncementExists($allianceId, $source->subjectId);
    }
}
