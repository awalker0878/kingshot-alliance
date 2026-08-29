<?php

declare(strict_types=1);

namespace App\ReadModels\NotificationDelivery\ValueObjects;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final readonly class AllianceNotificationRecipient
{
    public function __construct(
        public string $membershipId,
        public string $allianceId,
        public PlayerReference $player,
        public string $timezone,
    ) {}
}
