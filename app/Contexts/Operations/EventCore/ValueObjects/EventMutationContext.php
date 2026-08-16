<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\ValueObjects;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;

final readonly class EventMutationContext
{
    public function __construct(
        public Event $event,
        public EventTypeScope $typeScope,
        public Player $actor,
        public Alliance|Kingdom|Player $target,
    ) {}
}
