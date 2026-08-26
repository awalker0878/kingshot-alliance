<?php

declare(strict_types=1);

namespace App\ReadModels\EventManagement\Support;

use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

final class EventCommandOwnerReader
{
    /**
     * @param  Closure():array<string, mixed>  $query
     * @return array<string, mixed>|null
     */
    public function read(string $owner, Event $event, EventOccurrence $occurrence, Closure $query): ?array
    {
        try {
            return $query();
        } catch (Throwable $exception) {
            Log::warning('event_command.owner_projection_unavailable', [
                'owner' => $owner,
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'exception' => $exception::class,
            ]);

            return null;
        }
    }
}
