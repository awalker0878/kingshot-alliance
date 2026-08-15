<?php

declare(strict_types=1);

namespace App\Domain\Events\ValueObjects;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTypeScope;

final readonly class EventMutationContext
{
    public function __construct(
        public Event $event,
        public EventTypeScope $typeScope,
        public Player $actor,
        public Alliance|Kingdom|Player $target,
    ) {}
}
