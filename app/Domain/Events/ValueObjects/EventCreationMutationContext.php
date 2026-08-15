<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObjects;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Models\EventTypeScope;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;

final readonly class EventCreationMutationContext
{
    public function __construct(
        public EventTypeScope $typeScope,
        public Player $actor,
        public Alliance|Kingdom|Player $target,
    ) {}
}
