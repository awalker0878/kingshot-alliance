<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Data;

final readonly class PendingInvitation
{
    public function __construct(
        public string $invitationId,
        public string $allianceId,
        public string $allianceName,
        public string $playerId,
        public string $email,
        public ?string $expiresAt,
    ) {}
}
