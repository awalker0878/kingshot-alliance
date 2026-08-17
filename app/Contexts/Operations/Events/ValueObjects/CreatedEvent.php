<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\ValueObjects;

final readonly class CreatedEvent
{
    public function __construct(
        public string $eventId,
        public ?string $firstOccurrenceId,
    ) {}
}
