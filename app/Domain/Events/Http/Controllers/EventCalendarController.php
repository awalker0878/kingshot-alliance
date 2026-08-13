<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Events\Actions\CancelEventRegistration;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Queries\AllianceEventQuery;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Rallies\Actions\SaveMemberFormation;
use App\Domain\Rallies\Models\EventRecommendedFormation;
use App\Domain\Rallies\Models\MemberFormation;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Rallies\Models\RallyGuidanceRule;
use App\Domain\Rallies\ValueObjects\FormationComposition;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class EventCalendarController extends Controller
{
    public function index(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceEventQuery $events,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance();
        $membership = $context->membership();
        $occurrences = $events->calendar($alliance);
        $occurrenceIds = $occurrences->pluck('id')->all();

        $registrations = EventRegistration::query()
            ->where('alliance_id', $alliance->id)
            ->where('membership_id', $membership->id)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->get()
            ->keyBy('occurrence_id');

        /** @var list<array<string, mixed>> $items */
        $items = [];

        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;

            if (! $event instanceof Event) {
                throw new LogicException('An event occurrence must reference an event.');
            }

            $registration = $registrations->get($occurrence->id);
            $items[] = $this->occurrenceSummary(
                $occurrence,
                $event,
                $registration instanceof EventRegistration ? $registration : null,
            );
        }

        return Inertia::render('Alliance/Events/Index', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'timezone' => $alliance->timezone,
            ],
            'userTimezone' => (string) ($user->timezone ?: $alliance->timezone),
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::EventManage),
            'events' => $items,
            'exports' => [
                'csvUrl' => route('alliance.events.export'),
                'icalUrl' => route('alliance.events.ical'),
            ],
        ]);
    }

    public function show(
        Request $request,
        AllianceContext $context,
        AllianceAuthorization $authorization,
        AllianceEventQuery $events,
        string $occurrence,
    ): Response {
        $user = $this->user($request);
        $alliance = $context->alliance();
        $membership = $context->membership();
        $eventOccurrence = $events->occurrence($alliance, $occurrence);
        $event = $eventOccurrence->event;

        if (! $event instanceof Event) {
            throw new LogicException('An event occurrence must reference an event.');
        }

        $registration = EventRegistration::query()
            ->where('alliance_id', $alliance->id)
            ->where('occurrence_id', $eventOccurrence->id)
            ->where('membership_id', $membership->id)
            ->first();

        $registrationCounts = EventRegistration::query()
            ->where('alliance_id', $alliance->id)
            ->where('occurrence_id', $eventOccurrence->id)
            ->whereIn('status', [
                EventRegistrationStatus::Registered->value,
                EventRegistrationStatus::Waitlisted->value,
                EventRegistrationStatus::Attended->value,
                EventRegistrationStatus::NoShow->value,
            ])
            ->get()
            ->countBy(static fn (EventRegistration $item): string => $item->status->value);

        $recommendedFormations = EventRecommendedFormation::query()
            ->where('alliance_id', $alliance->id)
            ->where('occurrence_id', $eventOccurrence->id)
            ->with('guidanceRule')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /** @var list<array<string, mixed>> $formationData */
        $formationData = [];
        foreach ($recommendedFormations as $formation) {
            $guidance = $formation->guidanceRule;
            $formationData[] = [
                'id' => (string) $formation->id,
                'name' => (string) $formation->name,
                'assignmentRole' => (string) $formation->assignment_role,
                'heroes' => $formation->heroes ?? [],
                'infantryPercent' => (int) $formation->infantry_percent,
                'cavalryPercent' => (int) $formation->cavalry_percent,
                'archerPercent' => (int) $formation->archer_percent,
                'notes' => $formation->notes,
                'guidance' => $guidance instanceof RallyGuidanceRule ? [
                    'name' => (string) $guidance->name,
                    'source' => $guidance->source,
                    'rationale' => $guidance->rationale,
                    'effectiveFrom' => $guidance->effective_from?->toDateString(),
                    'effectiveUntil' => $guidance->effective_until?->toDateString(),
                ] : null,
            ];
        }

        $rallyGroups = RallyGroup::query()
            ->where('alliance_id', $alliance->id)
            ->where('occurrence_id', $eventOccurrence->id)
            ->with('assignments.membership.user')
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get();

        /** @var list<array<string, mixed>> $rallyData */
        $rallyData = [];
        foreach ($rallyGroups as $group) {
            /** @var list<array<string, mixed>> $assignments */
            $assignments = [];

            foreach ($group->assignments as $assignment) {
                if (! $assignment instanceof RallyAssignment) {
                    throw new LogicException('A rally assignment relation returned an unexpected model.');
                }

                $assignedMembership = $assignment->membership;
                $assignedUser = $assignedMembership instanceof AllianceMembership ? $assignedMembership->user : null;
                $assignments[] = [
                    'id' => (string) $assignment->id,
                    'membershipId' => (string) $assignment->membership_id,
                    'memberName' => $assignedUser instanceof User ? (string) $assignedUser->name : 'Unknown member',
                    'role' => $assignment->role->value,
                    'slotNumber' => $assignment->slot_number === null ? null : (int) $assignment->slot_number,
                    'status' => $assignment->status->value,
                    'participationRecordedAt' => $assignment->participation_recorded_at?->toIso8601String(),
                ];
            }

            $rallyData[] = [
                'id' => (string) $group->id,
                'name' => (string) $group->name,
                'maxJoiners' => $group->max_joiners === null ? null : (int) $group->max_joiners,
                'notes' => $group->notes,
                'assignments' => $assignments,
            ];
        }

        $savedFormations = MemberFormation::query()
            ->where('alliance_id', $alliance->id)
            ->where('membership_id', $membership->id)
            ->orderByDesc('is_default')
            ->orderBy('name')
            ->get();

        /** @var list<array<string, mixed>> $savedFormationData */
        $savedFormationData = [];
        foreach ($savedFormations as $saved) {
            $savedFormationData[] = [
                'id' => (string) $saved->id,
                'name' => (string) $saved->name,
                'heroes' => $saved->heroes ?? [],
                'infantryPercent' => (int) $saved->infantry_percent,
                'cavalryPercent' => (int) $saved->cavalry_percent,
                'archerPercent' => (int) $saved->archer_percent,
                'notes' => $saved->notes,
                'isDefault' => (bool) $saved->is_default,
            ];
        }

        return Inertia::render('Alliance/Events/Show', [
            'user' => [
                'name' => (string) $user->name,
                'email' => (string) $user->email,
            ],
            'alliance' => [
                'id' => $alliance->id,
                'name' => $alliance->name,
                'timezone' => $alliance->timezone,
            ],
            'userTimezone' => (string) ($user->timezone ?: $alliance->timezone),
            'canManage' => $authorization->allows($user, $alliance, PermissionKey::EventManage),
            'event' => [
                ...$this->occurrenceSummary(
                    $eventOccurrence,
                    $event,
                    $registration instanceof EventRegistration ? $registration : null,
                ),
                'instructions' => $event->instructions,
                'registeredCount' => (int) ($registrationCounts[EventRegistrationStatus::Registered->value] ?? 0)
                    + (int) ($registrationCounts[EventRegistrationStatus::Attended->value] ?? 0)
                    + (int) ($registrationCounts[EventRegistrationStatus::NoShow->value] ?? 0),
                'waitlistedCount' => (int) ($registrationCounts[EventRegistrationStatus::Waitlisted->value] ?? 0),
            ],
            'recommendedFormations' => $formationData,
            'rallyGroups' => $rallyData,
            'savedFormations' => $savedFormationData,
        ]);
    }

    public function register(
        Request $request,
        AllianceContext $context,
        RegisterForEvent $register,
        string $occurrence,
    ): RedirectResponse {
        $register->handle($this->user($request), $context->alliance(), $occurrence);

        return back()->with('status', 'event-registration-saved');
    }

    public function cancel(
        Request $request,
        AllianceContext $context,
        CancelEventRegistration $cancel,
        string $occurrence,
    ): RedirectResponse {
        $cancel->handle($this->user($request), $context->alliance(), $occurrence);

        return back()->with('status', 'event-registration-cancelled');
    }

    public function saveFormation(
        Request $request,
        AllianceContext $context,
        SaveMemberFormation $saveFormation,
    ): RedirectResponse {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'heroes' => ['nullable', 'array', 'max:5'],
            'heroes.*' => ['string', 'max:100'],
            'infantry_percent' => ['required', 'integer', 'between:0,100'],
            'cavalry_percent' => ['required', 'integer', 'between:0,100'],
            'archer_percent' => ['required', 'integer', 'between:0,100'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'is_default' => ['nullable', 'boolean'],
        ]);

        /** @var list<string> $heroes */
        $heroes = array_values(array_filter(
            $validated['heroes'] ?? [],
            static fn (mixed $hero): bool => is_string($hero),
        ));

        $saveFormation->handle(
            actor: $this->user($request),
            alliance: $context->alliance(),
            name: (string) $validated['name'],
            composition: new FormationComposition(
                (int) $validated['infantry_percent'],
                (int) $validated['cavalry_percent'],
                (int) $validated['archer_percent'],
            ),
            heroes: $heroes,
            notes: isset($validated['notes']) ? (string) $validated['notes'] : null,
            isDefault: (bool) ($validated['is_default'] ?? false),
        );

        return back()->with('status', 'formation-saved');
    }

    public function export(Request $request, AllianceContext $context, AllianceEventQuery $events): HttpResponse
    {
        $this->user($request);
        $alliance = $context->alliance();
        $occurrences = $events->calendar($alliance, pastDays: 0, futureDays: 366);

        $stream = fopen('php://temp', 'r+');
        if ($stream === false) {
            abort(500, 'Unable to create event export.');
        }

        fputcsv($stream, ['event', 'starts_at_utc', 'ends_at_utc', 'alliance_timezone', 'capacity', 'status']);

        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event) {
                continue;
            }

            fputcsv($stream, [
                $event->title,
                $occurrence->starts_at->toIso8601String(),
                $occurrence->ends_at->toIso8601String(),
                $event->timezone,
                $occurrence->capacity,
                $occurrence->status->value,
            ]);
        }

        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return response($csv === false ? '' : $csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.Str::slug((string) $alliance->name).'-events.csv"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function ical(Request $request, AllianceContext $context, AllianceEventQuery $events): HttpResponse
    {
        $this->user($request);
        $alliance = $context->alliance();
        $occurrences = $events->calendar($alliance, pastDays: 0, futureDays: 366);
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:-//Kingshot Alliance//Events//EN',
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:'.$this->icalEscape((string) $alliance->name).' Events',
            'X-WR-TIMEZONE:'.$this->icalEscape((string) $alliance->timezone),
        ];

        foreach ($occurrences as $occurrence) {
            $event = $occurrence->event;
            if (! $event instanceof Event) {
                continue;
            }

            $lines[] = 'BEGIN:VEVENT';
            $lines[] = 'UID:'.$occurrence->id.'@kingshot-alliance';
            $lines[] = 'DTSTAMP:'.now()->utc()->format('Ymd\\THis\\Z');
            $lines[] = 'DTSTART:'.$occurrence->starts_at->copy()->utc()->format('Ymd\\THis\\Z');
            $lines[] = 'DTEND:'.$occurrence->ends_at->copy()->utc()->format('Ymd\\THis\\Z');
            $lines[] = 'SUMMARY:'.$this->icalEscape((string) $event->title);
            if ($event->instructions !== null && trim((string) $event->instructions) !== '') {
                $lines[] = 'DESCRIPTION:'.$this->icalEscape((string) $event->instructions);
            }
            $lines[] = 'END:VEVENT';
        }

        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines)."\r\n", 200, [
            'Content-Type' => 'text/calendar; charset=UTF-8',
            'Content-Disposition' => 'inline; filename="'.Str::slug((string) $alliance->name).'-events.ics"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    /** @return array<string, mixed> */
    private function occurrenceSummary(
        EventOccurrence $occurrence,
        Event $event,
        ?EventRegistration $registration,
    ): array {
        return [
            'id' => (string) $occurrence->id,
            'eventId' => (string) $event->id,
            'title' => (string) $event->title,
            'startsAt' => $occurrence->starts_at->toIso8601String(),
            'endsAt' => $occurrence->ends_at->toIso8601String(),
            'allianceTimezone' => (string) $event->timezone,
            'capacity' => $occurrence->capacity === null ? null : (int) $occurrence->capacity,
            'status' => $occurrence->status->value,
            'registrationOpensAt' => $occurrence->registration_opens_at?->toIso8601String(),
            'registrationClosesAt' => $occurrence->registration_closes_at?->toIso8601String(),
            'registration' => $registration === null ? null : [
                'status' => $registration->status->value,
                'waitlistPosition' => $registration->waitlist_position === null
                    ? null
                    : (int) $registration->waitlist_position,
            ],
        ];
    }

    private function icalEscape(string $value): string
    {
        return str_replace(
            ['\\', ';', ',', "\r\n", "\r", "\n"],
            ['\\\\', '\\;', '\\,', '\\n', '\\n', '\\n'],
            $value,
        );
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
