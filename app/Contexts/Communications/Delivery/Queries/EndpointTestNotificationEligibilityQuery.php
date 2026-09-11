<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Queries;

use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;

final readonly class EndpointTestNotificationEligibilityQuery
{
    public function allows(NotificationSource $source): bool
    {
        return $source->playerId !== null && $source->subjectType === 'notification_endpoint'
            && $source->subjectId !== null && NotificationEndpoint::query()->whereKey($source->subjectId)
                ->where('recipient_user_id', $source->recipientUserId)->where('player_id', $source->playerId)
                ->where('enabled', true)->exists();
    }
}
