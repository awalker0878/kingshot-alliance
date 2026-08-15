<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Queries;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\EventCore\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\EventCore\Services\EventTargetResolver;
use App\Contexts\Operations\EventCore\Services\EventVisibilityResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EventCalendarQuery
{
    public function __construct(
        private EventVisibilityResolver $visibility,
        private EventAuthorization $authorization,
        private EventTargetResolver $targets,
    ) {}

    /** @return Collection<int, EventOccurrence> */
    public function forAlliance(Player $actor, Alliance $alliance, int $pastDays = 0, int $futureDays = 30): Collection
    {
        $pastDays = max(0, min($pastDays, 31));
        $futureDays = max(1, min($futureDays, 366));

        $this->authorization->authorize(
            $actor,
            EventScope::Alliance,
            $alliance,
            OperationsPermission::EventAllianceView,
        );

        return EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [now()->subDays($pastDays), now()->addDays($futureDays)])
            ->whereHas('event', static function (Builder $query) use ($alliance): void {
                $query
                    ->where('scope', EventScope::Alliance->value)
                    ->where('alliance_id', $alliance->id);
            })
            ->with(['event.eventType'])
            ->orderBy('starts_at')
            ->limit(100)
            ->get();
    }

    /** @return Collection<int, EventOccurrence> */
    public function calendar(Player $actor, int $pastDays = 7, int $futureDays = 90): Collection
    {
        $pastDays = max(0, min($pastDays, 31));
        $futureDays = max(1, min($futureDays, 366));
        $targets = $this->visibility->targetIds($actor);

        return EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->whereBetween('starts_at', [now()->subDays($pastDays), now()->addDays($futureDays)])
            ->whereHas('event', function (Builder $query) use ($targets): void {
                $query->where(function (Builder $scopeQuery) use ($targets): void {
                    $hasTargets = false;
                    if ($targets['alliance'] !== []) {
                        $scopeQuery->where(function (Builder $q) use ($targets): void {
                            $q->where('scope', 'alliance')->whereIn('alliance_id', $targets['alliance']);
                        });
                        $hasTargets = true;
                    }
                    if ($targets['player'] !== []) {
                        $method = $hasTargets ? 'orWhere' : 'where';
                        $scopeQuery->{$method}(function (Builder $q) use ($targets): void {
                            $q->where('scope', 'player')->whereIn('player_id', $targets['player']);
                        });
                        $hasTargets = true;
                    }
                    if ($targets['kingdom'] !== []) {
                        $method = $hasTargets ? 'orWhere' : 'where';
                        $scopeQuery->{$method}(function (Builder $q) use ($targets): void {
                            $q->where('scope', 'kingdom')->whereIn('kingdom_id', $targets['kingdom']);
                        });
                        $hasTargets = true;
                    }
                    if (! $hasTargets) {
                        $scopeQuery->whereRaw('1 = 0');
                    }
                });
            })
            ->with([
                'event.eventType',
                'event.typeScope.capabilities',
                'event.alliance',
                'event.kingdom',
                'event.player',
            ])
            ->orderBy('starts_at')
            ->limit(500)
            ->get();
    }

    public function occurrence(Player $actor, string $occurrenceId): EventOccurrence
    {
        $occurrence = EventOccurrence::query()
            ->whereKey($occurrenceId)
            ->with([
                'event.eventType',
                'event.typeScope.capabilities',
                'event.alliance',
                'event.kingdom',
                'event.player',
            ])
            ->firstOrFail();

        $event = $occurrence->event;
        if (! $event instanceof Event) {
            throw (new ModelNotFoundException)->setModel(Event::class);
        }

        $this->authorize($actor, $event, (string) $event->typeScope->view_permission_key);

        return $occurrence;
    }

    public function eventForManage(Player $actor, string $eventId): Event
    {
        $event = Event::query()
            ->whereKey($eventId)
            ->with([
                'eventType',
                'typeScope.capabilities',
                'alliance',
                'kingdom',
                'player',
                'occurrences',
            ])
            ->firstOrFail();

        $this->authorize($actor, $event, (string) $event->typeScope->manage_permission_key);

        return $event;
    }

    private function authorize(Player $actor, Event $event, string $permissionKey): void
    {
        $target = $this->targets->forEvent($event);
        $this->authorization->authorize(
            $actor,
            $event->scope,
            $target,
            OperationsPermission::from($permissionKey),
        );
    }
}
