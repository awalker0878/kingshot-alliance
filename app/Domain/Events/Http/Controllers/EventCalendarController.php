<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Queries\EventAttentionQuery;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Domain\Events\Queries\EventObjectiveQuery;
use App\Domain\Events\Queries\EventParticipationQuery;
use App\Domain\Events\Queries\EventPlayerIntelligenceQuery;
use App\Domain\Events\Queries\EventPhasePollQuery;
use App\Domain\Events\Queries\EventRosterQuery;
use App\Domain\Events\Queries\EventResultQuery;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventCreationContextResolver;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventRegistrationWindow;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventVisibilityResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Notifications\Queries\EventReminderInboxQuery;
use App\Domain\Rallies\Queries\EventRallyQuery;
use App\Domain\Platform\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\Response as IlluminateResponse;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class EventCalendarController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function index(
        Request $request,
        EventCalendarQuery $query,
        EventAttentionQuery $attention,
        EventReminderInboxQuery $reminderInbox,
        EventCreationContextResolver $creationContexts,
        EventVisibilityResolver $visibility,
        EventTargetResolver $targets,
    ): Response {
        $user = $this->user($request);
        $actor = $this->player();
        $occurrences = $query->calendar($actor);
        $manageableTargets = $visibility->manageableTargetIds($actor);

        return Inertia::render('Events/Index', [
            'user' => $this->identity($user),
            'userTimezone' => (string) ($user->timezone ?: 'UTC'),
            'events' => $occurrences->map(fn (EventOccurrence $occurrence): array => $this->summary(
                $occurrence,
                $targets,
                $this->canManageTarget($occurrence, $manageableTargets),
            ))->all(),
            'attention' => $attention->for($actor),
            'reminders' => $reminderInbox->for($user),
            'canCreate' => $creationContexts->forPlayer($actor) !== [],
            'status' => $request->session()->get('status'),
        ]);
    }

    public function show(
        Request $request,
        string $occurrence,
        EventCalendarQuery $query,
        EventParticipationQuery $participation,
        EventPhasePollQuery $phasePolls,
        EventObjectiveQuery $objectives,
        EventResultQuery $results,
        EventPlayerIntelligenceQuery $intelligence,
        EventRosterQuery $rosters,
        EventRallyQuery $rallies,
        EventParticipantAuthorization $participantAuthorization,
        EventRegistrationWindow $registrationWindow,
        EventAuthorization $authorization,
        EventTargetResolver $targets,
    ): Response {
        $user = $this->user($request);
        $actor = $this->player();
        $eventOccurrence = $query->occurrence($actor, $occurrence);
        $event = $eventOccurrence->event;
        if (! $event instanceof Event) {
            throw new LogicException('An event occurrence must reference an event.');
        }

        $target = $targets->forEvent($event);
        $canManage = $authorization->allows(
            $actor,
            $event->scope,
            $target,
            PermissionKey::from((string) $event->typeScope->manage_permission_key),
        );

        $eligibleActivePlayer = $participantAuthorization->eligible($event, $actor)
            ? $actor
            : null;
        $playerParticipation = null;
        if ($eligibleActivePlayer instanceof Player) {
            $window = $registrationWindow->for($event, $eventOccurrence);
            $playerParticipation = [
                'playerId' => (string) $eligibleActivePlayer->id,
                'playerName' => (string) $eligibleActivePlayer->current_name,
                ...$participation->forPlayer($eventOccurrence, $eligibleActivePlayer),
                'registrationWindow' => [
                    'opensAt' => $window['opens_at']?->toIso8601String(),
                    'closesAt' => $window['closes_at']->toIso8601String(),
                    'isOpen' => $window['is_open'],
                ],
            ];
        }

        return Inertia::render('Events/Show', [
            'user' => $this->identity($user),
            'userTimezone' => (string) ($user->timezone ?: 'UTC'),
            'event' => [
                ...$this->summary($eventOccurrence, $targets, $canManage),
                'instructions' => $event->instructions,
                'settings' => $event->settings ?? [],
                'capacity' => $event->capacity,
                'registrationOpensMinutesBefore' => $event->registration_opens_minutes_before,
                'registrationClosesMinutesBefore' => $event->registration_closes_minutes_before,
                'recurrenceFrequency' => $event->recurrence_frequency->value,
                'recurrenceInterval' => $event->recurrence_interval,
                'recurrenceUntil' => $event->recurrence_until?->toIso8601String(),
                'canManage' => $canManage,
                'participation' => $playerParticipation,
                'operations' => $phasePolls->forOccurrence($eventOccurrence, $eligibleActivePlayer),
                'battlePlan' => $objectives->forOccurrence($eventOccurrence, $eligibleActivePlayer),
                'results' => $results->forOccurrence($eventOccurrence, $eligibleActivePlayer),
                'playerIntelligence' => $eligibleActivePlayer instanceof Player ? $intelligence->forPlayer($event, $eligibleActivePlayer) : null,
                'rosters' => $eligibleActivePlayer instanceof Player ? $rosters->forPlayer($eventOccurrence, $eligibleActivePlayer) : [],
                'rallies' => $rallies->forOccurrence($eventOccurrence, $eligibleActivePlayer),
            ],
        ]);
    }

    public function export(Request $request, EventCalendarQuery $query, EventTargetResolver $targets): StreamedResponse
    {
        $this->user($request);
        $actor = $this->player();
        $occurrences = $query->calendar($actor, pastDays: 31, futureDays: 366);

        return response()->streamDownload(function () use ($occurrences, $targets): void {
            $handle = fopen('php://output', 'wb');
            if ($handle === false) {
                return;
            }

            fputcsv($handle, ['event_type', 'title', 'scope', 'target', 'starts_at_utc', 'ends_at_utc', 'timezone', 'status']);
            foreach ($occurrences as $occurrence) {
                $event = $occurrence->event;
                if (! $event instanceof Event) {
                    continue;
                }
                fputcsv($handle, [
                    (string) $event->eventType->slug,
                    $event->title ?: (string) $event->eventType->name_key,
                    $event->scope->value,
                    $targets->label($targets->forEvent($event)),
                    $occurrence->starts_at->utc()->toIso8601String(),
                    $occurrence->ends_at->utc()->toIso8601String(),
                    (string) $event->timezone,
                    $occurrence->status->value,
                ]);
            }

            fclose($handle);
        }, 'kingshot-events.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public function ical(Request $request, EventCalendarQuery $query, EventTargetResolver $targets): IlluminateResponse
    {
        $this->user($request);
        $actor = $this->player();
        $occurrences = $query->calendar($actor, pastDays: 31, futureDays: 366);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kingshot Alliance//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
        ];

        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event) {
                continue;
            }

            $title = $event->title ?: (string) $event->eventType->slug;
            $target = $targets->label($targets->forEvent($event));
            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$this->icsEscape((string) $occurrence->id).'@kingshot-alliance';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTSTART:'.$occurrence->starts_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'DTEND:'.$occurrence->ends_at->utc()->format('Ymd\THis\Z');
            $lines[] = 'SUMMARY:'.$this->icsEscape($title);
            $lines[] = 'DESCRIPTION:'.$this->icsEscape(trim(($event->instructions ?? '')."\n".$event->scope->value.' · '.$target));
            $lines[] = 'END:VEVENT';
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="kingshot-events.ics"',
        ]);
    }

    /** @return array<string, mixed> */
    private function summary(
        EventOccurrence $occurrence,
        EventTargetResolver $targets,
        bool $canManage,
    ): array {
        $event = $occurrence->event;
        if (! $event instanceof Event) {
            throw new LogicException('An event occurrence must reference an event.');
        }

        $target = $targets->forEvent($event);

        return [
            'id' => (string) $occurrence->id,
            'eventId' => (string) $event->id,
            'eventTypeSlug' => (string) $event->eventType->slug,
            'nameKey' => (string) $event->eventType->name_key,
            'title' => $event->title,
            'scope' => $event->scope->value,
            'targetId' => (string) $target->id,
            'targetLabel' => $targets->label($target),
            'startsAt' => $occurrence->starts_at->toIso8601String(),
            'endsAt' => $occurrence->ends_at->toIso8601String(),
            'timezone' => (string) $event->timezone,
            'status' => $occurrence->status->value,
            'capabilities' => $event->typeScope->capabilities
                ->pluck('capability')
                ->map(static fn (EventCapability $capability): string => $capability->value)
                ->sort()
                ->values()
                ->all(),
            'canManage' => $canManage,
        ];
    }

    /** @param array{alliance:list<string>,player:list<string>,kingdom:list<string>} $manageableTargets */
    private function canManageTarget(EventOccurrence $occurrence, array $manageableTargets): bool
    {
        $event = $occurrence->event;
        if (! $event instanceof Event) {
            return false;
        }

        return match ($event->scope) {
            \App\Domain\Events\Enums\EventScope::Player => $event->player_id !== null && in_array((string) $event->player_id, $manageableTargets['player'], true),
            \App\Domain\Events\Enums\EventScope::Alliance => $event->alliance_id !== null && in_array((string) $event->alliance_id, $manageableTargets['alliance'], true),
            \App\Domain\Events\Enums\EventScope::Kingdom => $event->kingdom_id !== null && in_array((string) $event->kingdom_id, $manageableTargets['kingdom'], true),
        };
    }

    private function icsEscape(string $value): string
    {
        return str_replace(["\\", ";", ",", "\r\n", "\r", "\n"], ["\\\\", '\\;', '\\,', '\\n', '\\n', '\\n'], $value);
    }

    private function player(): Player
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before opening Events.');

        return $player;
    }

    /** @return array{name:string,email:string} */
    private function identity(User $user): array
    {
        return ['name' => (string) $user->name, 'email' => (string) $user->email];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
