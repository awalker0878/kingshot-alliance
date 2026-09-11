<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Security\Queries;

use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;

/** Account security facts do not require Alliance membership or a Governor. */
final readonly class SecurityNotificationEligibilityQuery
{
    public function allows(NotificationSource $source): bool
    {
        $event = $source->metadata['event'] ?? null;

        return $source->playerId === null
            && $source->subjectType === 'account_security_event'
            && is_string($event) && $event !== ''
            && $source->subjectId === mb_substr($event, 0, 64);
    }
}
