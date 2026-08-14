<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Events\Actions\CancelEvent;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\CreateEventFromTemplate;
use App\Domain\Events\Actions\CreateEventTemplate;
use App\Domain\Events\Actions\UpdateEvent;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventCalendarQuery;
use App\Domain\Events\Queries\EventObjectiveQuery;
use App\Domain\Events\Queries\EventParticipationQuery;
use App\Domain\Events\Queries\EventPlayerIntelligenceQuery;
use App\Domain\Events\Queries\EventPhasePollQuery;
use App\Domain\Events\Queries\EventRosterQuery;
use App\Domain\Events\Queries\EventResultQuery;
use App\Domain\Events\Queries\EventTemplateQuery;
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventCreationContextResolver;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Events\Services\EventTypeDefaultsResolver;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Kingdoms\Services\PlayerContext;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Domain\Platform\Http\Controllers\Controller;
use App\Domain\Rallies\Queries\EventRallyQuery;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class EventManagementController extends Controller
{
    public function __construct(private readonly PlayerContext $playerContext) {}

    public function create(
        Request $request,
        EventCreationContextResolver $contexts,
        EventTypeRegistry $types,
        EventTypeDefaultsResolver $defaults,
        EventTemplateQuery $templates,
        EventTargetResolver $targets,
    ): Response {
        $user = $this->user($request);
        $actor = $this->player();
        $creationContexts = $contexts->forPlayer($actor);
        $typesByScope = [];

        foreach (EventScope::cases() as $scope) {
            $typesByScope[$scope->value] = $types->activeForScope($scope)
                ->map(function (EventType $type) use ($scope, $types, $defaults): array {
                    $configuration = $types->scope($type, $scope);

                    return [
                        'id' => (string) $type->id,
                        'scopeConfigurationId' => (string) $configuration->id,
                        'slug' => (string) $type->slug,
                        'nameKey' => (string) $type->name_key,
                        'descriptionKey' => $type->description_key,
                        'category' => $type->category->value,
                        'iconKey' => $type->icon_key,
                        'defaults' => $defaults->resolve($configuration),
                    ];
                })
                ->all();
        }

        return Inertia::render('Events/Create', [
            'user' => $this->identity($user),
            'contexts' => $creationContexts,
            'typesByScope' => $typesByScope,
            'templates' => $templates->available($actor)->map(function (EventTemplate $template) use ($targets): array {
                $target = $targets->forTemplate($template);

                return [
                    'id' => (string) $template->id,
                    'name' => (string) $template->name,
                    'nameKey' => (string) $template->eventType->name_key,
                    'scope' => $template->scope->value,
                    'targetId' => (string) $target->id,
                    'targetLabel' => $targets->label($target),
                    'timezone' => (string) $template->timezone,
                    'recurrenceFrequency' => $template->recurrence_frequency->value,
                    'recurrenceInterval' => (int) $template->recurrence_interval,
                ];
            })->values()->all(),
        ]);
    }

    public function store(
        Request $request,
        CreateEvent $create,
        EventTargetResolver $targets,
        EventTypeRegistry $types,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $validated = $this->validateEvent($request, creating: true);
        $scope = EventScope::from((string) $validated['scope']);
        $target = $targets->resolve($scope, (string) $validated['target_id']);
        $eventType = EventType::query()->whereKey((string) $validated['event_type_id'])->firstOrFail();
        $configuration = $types->scope($eventType, $scope);
        $timezone = $targets->defaultTimezone($actor, $target);

        $event = $create->handle(
            actor: $actor,
            configuration: $configuration,
            target: $target,
            firstLocalStart: CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['first_local_start'], $timezone),
            title: $validated['title'] ?? null,
            instructions: $validated['instructions'] ?? null,
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before']) ? (int) $validated['registration_opens_minutes_before'] : null,
            registrationClosesMinutesBefore: isset($validated['registration_closes_minutes_before']) ? (int) $validated['registration_closes_minutes_before'] : null,
            frequency: isset($validated['recurrence_frequency']) ? RecurrenceFrequency::from((string) $validated['recurrence_frequency']) : null,
            recurrenceInterval: isset($validated['recurrence_interval']) ? (int) $validated['recurrence_interval'] : null,
            recurrenceUntilLocal: isset($validated['recurrence_until_local']) && $validated['recurrence_until_local'] !== null
                ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['recurrence_until_local'], $timezone)
                : null,
            publish: (bool) ($validated['publish'] ?? true),
        );

        $occurrence = $event->occurrences->sortBy('starts_at')->first();

        if ($occurrence === null) {
            return redirect()->route('events.index')->with('status', 'event-created');
        }

        return redirect()->route('events.show', ['occurrence' => $occurrence->id])
            ->with('status', 'event-created');
    }

    public function manage(
        Request $request,
        string $event,
        EventCalendarQuery $query,
        EventParticipationQuery $participation,
        EventPhasePollQuery $phasePolls,
        EventObjectiveQuery $objectives,
        EventResultQuery $results,
        EventPlayerIntelligenceQuery $intelligence,
        EventRosterQuery $rosters,
        EventRallyQuery $rallies,
        EventCapabilityResolver $capabilities,
    ): Response
    {
        $user = $this->user($request);
        $actor = $this->player();
        $record = $query->eventForManage($actor, $event);
        $capabilityKeys = $capabilities->keys($record->typeScope);
        $reminderAudiences = [EventReminderAudience::AllScopePlayers->value];
        if ($record->scope === EventScope::Player) {
            $reminderAudiences[] = EventReminderAudience::Target->value;
        }
        if (in_array(EventCapability::Responses->value, $capabilityKeys, true)) {
            $reminderAudiences[] = EventReminderAudience::Responded->value;
        }
        if (in_array(EventCapability::Registration->value, $capabilityKeys, true)) {
            $reminderAudiences[] = EventReminderAudience::Registered->value;
        }
        if (in_array(EventCapability::Rosters->value, $capabilityKeys, true)) {
            $reminderAudiences[] = EventReminderAudience::Rostered->value;
        }

        return Inertia::render('Events/Manage', [
            'user' => $this->identity($user),
            'event' => $this->managementPayload($record),
            'participants' => $participation->management($record),
            'operations' => $phasePolls->management($record),
            'battlePlan' => $objectives->management($record),
            'resultOperations' => $results->management($record),
            'playerIntelligence' => $intelligence->forEvent($record),
            'rosterOperations' => $rosters->management($record),
            'rallyOperations' => $rallies->management($record),
            'reminderAudiences' => array_values(array_unique($reminderAudiences)),
            'reminderRules' => EventReminderRule::query()
                ->where('event_id', $record->id)
                ->orderBy('minutes_before')
                ->get()
                ->map(static fn (EventReminderRule $rule): array => [
                    'id' => (string) $rule->id,
                    'pollId' => $rule->poll_id === null ? null : (string) $rule->poll_id,
                    'trigger' => $rule->trigger_type->value,
                    'minutesBefore' => (int) $rule->minutes_before,
                    'audience' => $rule->audience->value,
                    'channel' => (string) $rule->channel,
                    'enabled' => (bool) $rule->is_enabled,
                ])->all(),
        ]);
    }

    public function update(
        Request $request,
        string $event,
        EventCalendarQuery $query,
        UpdateEvent $update,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $query->eventForManage($actor, $event);
        $validated = $this->validateEvent($request, creating: false);

        $update->handle(
            actor: $actor,
            event: $record,
            firstLocalStart: isset($validated['first_local_start'])
                ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['first_local_start'], $record->timezone)
                : null,
            title: array_key_exists('title', $validated) ? $validated['title'] : null,
            instructions: array_key_exists('instructions', $validated) ? $validated['instructions'] : null,
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before']) ? (int) $validated['registration_opens_minutes_before'] : null,
            registrationClosesMinutesBefore: isset($validated['registration_closes_minutes_before']) ? (int) $validated['registration_closes_minutes_before'] : null,
            frequency: isset($validated['recurrence_frequency']) ? RecurrenceFrequency::from((string) $validated['recurrence_frequency']) : null,
            recurrenceInterval: isset($validated['recurrence_interval']) ? (int) $validated['recurrence_interval'] : null,
            recurrenceUntilLocal: isset($validated['recurrence_until_local']) && $validated['recurrence_until_local'] !== null
                ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['recurrence_until_local'], $record->timezone)
                : null,
        );

        return back()->with('status', 'event-updated');
    }

    public function cancel(
        Request $request,
        string $event,
        EventCalendarQuery $query,
        CancelEvent $cancel,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = $query->eventForManage($actor, $event);
        $cancel->handle($actor, $record);

        return redirect()->route('events.index')->with('status', 'event-cancelled');
    }

    public function storeTemplate(
        Request $request,
        CreateEventTemplate $create,
        EventTargetResolver $targets,
        EventTypeRegistry $types,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $validated = $request->validate([
            'scope' => ['required', Rule::enum(EventScope::class)],
            'target_id' => ['required', 'string'],
            'event_type_id' => ['required', 'string'],
            'name' => ['required', 'string', 'max:120'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'capacity' => ['nullable', 'integer', 'between:1,100000'],
            'registration_opens_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'registration_closes_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'between:1,52'],
            'settings' => ['nullable', 'array'],
        ]);
        $scope = EventScope::from((string) $validated['scope']);
        $target = $targets->resolve($scope, (string) $validated['target_id']);
        $type = EventType::query()->whereKey((string) $validated['event_type_id'])->firstOrFail();

        $create->handle(
            actor: $actor,
            configuration: $types->scope($type, $scope),
            target: $target,
            name: (string) $validated['name'],
            instructions: $validated['instructions'] ?? null,
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before']) ? (int) $validated['registration_opens_minutes_before'] : null,
            registrationClosesMinutesBefore: isset($validated['registration_closes_minutes_before']) ? (int) $validated['registration_closes_minutes_before'] : null,
            frequency: isset($validated['recurrence_frequency']) ? RecurrenceFrequency::from((string) $validated['recurrence_frequency']) : null,
            recurrenceInterval: isset($validated['recurrence_interval']) ? (int) $validated['recurrence_interval'] : null,
            settings: isset($validated['settings']) && is_array($validated['settings']) ? $validated['settings'] : [],
        );

        return back()->with('status', 'event-template-created');
    }

    public function storeFromTemplate(
        Request $request,
        string $template,
        CreateEventFromTemplate $create,
    ): RedirectResponse {
        $this->user($request);
        $actor = $this->player();
        $record = EventTemplate::query()->whereKey($template)->where('is_active', true)->firstOrFail();
        $validated = $request->validate([
            'first_local_start' => ['required', 'date_format:Y-m-d\\TH:i'],
            'recurrence_until_local' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'title' => ['nullable', 'string', 'max:160'],
        ]);

        $event = $create->handle(
            actor: $actor,
            template: $record,
            firstLocalStart: CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['first_local_start'], $record->timezone),
            recurrenceUntilLocal: isset($validated['recurrence_until_local'])
                ? CarbonImmutable::createFromFormat('Y-m-d\\TH:i', (string) $validated['recurrence_until_local'], $record->timezone)
                : null,
            title: $validated['title'] ?? null,
        );

        $occurrence = $event->occurrences->sortBy('starts_at')->first();

        if ($occurrence === null) {
            return redirect()->route('events.index')->with('status', 'event-created');
        }

        return redirect()->route('events.show', ['occurrence' => $occurrence->id])
            ->with('status', 'event-created');
    }

    /** @return array<string, mixed> */
    private function validateEvent(Request $request, bool $creating): array
    {
        return $request->validate([
            'scope' => [$creating ? 'required' : 'sometimes', Rule::enum(EventScope::class)],
            'target_id' => [$creating ? 'required' : 'sometimes', 'string'],
            'event_type_id' => [$creating ? 'required' : 'sometimes', 'string'],
            'first_local_start' => [$creating ? 'required' : 'sometimes', 'date_format:Y-m-d\\TH:i'],
            'title' => ['nullable', 'string', 'max:160'],
            'instructions' => ['nullable', 'string', 'max:10000'],
            'duration_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'capacity' => ['nullable', 'integer', 'between:1,100000'],
            'registration_opens_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'registration_closes_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'recurrence_frequency' => ['nullable', Rule::enum(RecurrenceFrequency::class)],
            'recurrence_interval' => ['nullable', 'integer', 'between:1,52'],
            'recurrence_until_local' => ['nullable', 'date_format:Y-m-d\\TH:i'],
            'publish' => ['nullable', 'boolean'],
        ]);
    }

    /** @return array<string, mixed> */
    private function managementPayload(Event $event): array
    {
        return [
            'id' => (string) $event->id,
            'eventTypeId' => (string) $event->event_type_id,
            'targetId' => match ($event->scope) {
                EventScope::Player => (string) $event->player_id,
                EventScope::Alliance => (string) $event->alliance_id,
                EventScope::Kingdom => (string) $event->kingdom_id,
            },
            'nameKey' => (string) $event->eventType->name_key,
            'title' => $event->title,
            'scope' => $event->scope->value,
            'timezone' => (string) $event->timezone,
            'firstLocalStart' => $event->starts_at->setTimezone($event->timezone)->format('Y-m-d\\TH:i'),
            'instructions' => $event->instructions,
            'durationMinutes' => $event->duration_minutes,
            'capacity' => $event->capacity,
            'registrationOpensMinutesBefore' => $event->registration_opens_minutes_before,
            'registrationClosesMinutesBefore' => $event->registration_closes_minutes_before,
            'recurrencePolicy' => $event->recurrence_policy->value,
            'recurrenceFrequency' => $event->recurrence_frequency->value,
            'recurrenceInterval' => $event->recurrence_interval,
            'recurrenceUntilLocal' => $event->recurrence_until?->setTimezone($event->timezone)->format('Y-m-d\\TH:i'),
            'settings' => $event->settings ?? [],
            'capabilities' => $event->typeScope->capabilities
                ->pluck('capability')
                ->map(static fn (EventCapability $capability): string => $capability->value)
                ->sort()
                ->values()
                ->all(),
            'createdByPlayerId' => $event->created_by_player_id,
            'updatedByPlayerId' => $event->updated_by_player_id,
            'occurrences' => $event->occurrences->sortBy('starts_at')->map(static fn ($occurrence): array => [
                'id' => (string) $occurrence->id,
                'startsAt' => $occurrence->starts_at->toIso8601String(),
                'endsAt' => $occurrence->ends_at->toIso8601String(),
                'status' => $occurrence->status->value,
            ])->values()->all(),
        ];
    }

    /** @return array{name:string,email:string} */
    private function identity(User $user): array
    {
        return ['name' => (string) $user->name, 'email' => (string) $user->email];
    }

    private function player(): Player
    {
        $player = $this->playerContext->playerOrNull();
        abort_unless($player instanceof Player, 409, 'Select a Player before performing Event operations.');

        return $player;
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
