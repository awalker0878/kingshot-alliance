<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;

final readonly class EventScopeAuthorityFacts
{
    /** @param list<AllianceAuthorityFacts> $playerManagerAllianceFacts */
    public function __construct(
        public ?AllianceAuthorityFacts $allianceFacts = null,
        public ?KingdomAuthorityFacts $kingdomFacts = null,
        public array $playerManagerAllianceFacts = [],
    ) {}
}
