<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\ValueObjects;

use App\Contexts\Alliance\Membership\Enums\RosterState;

final readonly class RosterEntryReference
{
    public function __construct(
        public string $rosterEntryId,
        public string $allianceId,
        public string $playerId,
        public string $observedName,
        public RosterState $stateObservedAtRead,
        public ?string $gameRole,
        public ?string $joinedAt,
        public ?string $managerNotes,
        public string $source,
    ) {}
}
