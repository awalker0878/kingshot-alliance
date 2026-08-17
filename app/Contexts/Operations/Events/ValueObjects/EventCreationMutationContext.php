<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\EventTypeScope;

/**
 * Transaction-time state used while creating an Event.
 *
 * EventTypeScope is Operations-owned persistence. Actor, target, and mutable
 * authority facts are immutable values resolved from their owning contexts.
 */
final readonly class EventCreationMutationContext
{
    public function __construct(
        public EventTypeScope $typeScope,
        public PlayerReference $actor,
        public EventTargetReference $target,
        public EventScopeAuthorityFacts $authority,
    ) {}
}
