<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Http\Controllers;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Services\PlayerContext;
use App\Contexts\Operations\Events\Actions\CancelEvent;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Actions\CreateEventFromTemplate;
use App\Contexts\Operations\Events\Actions\CreateEventTemplate;
use App\Contexts\Operations\Events\Actions\UpdateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventTemplate;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Queries\EventCalendarQuery;
use App\Contexts\Operations\Events\Queries\EventTemplateQuery;
use App\Contexts\Operations\Events\Services\EventCreationContextResolver;
use App\Contexts\Operations\Events\Services\EventTargetResolver;
use App\Contexts\Operations\Events\Services\EventTypeDefaultsResolver;
use App\Contexts\Operations\Events\Services\EventTypeRegistry;
use App\Shared\Infrastructure\Http\Controller;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

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
                $eventType = $template->eventType;
                if (! $eventType instanceof EventType) {
                    throw new LogicException('Event template must reference an Event type.');
                }

                return [
                    'id' => (string) $template->id,
                    'name' => (string) $template->name,
                    'nameKey' => (string) $eventType->name_key,
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
            firstLocalStart: $this->requiredTime((string) $validated['first_local_start'], $timezone, 'first_local_start'),
            title: $validated['title'] ?? null,
            instructions: $validated['instructions'] ?? null,
            durationMinutes: isset($validated['duration_minutes']) ? (int) $validated['duration_minutes'] : null,
            capacity: isset($validated['capacity']) ? (int) $validated['capacity'] : null,
            registrationOpensMinutesBefore: isset($validated['registration_opens_minutes_before']) ? (int) $validated['registration_opens_minutes_before'] : null,
            registrationClosesMinutesBefore: isset($validated['registration_closes_minutes_before']) ? (int) $validated['registration_closes_minutes_before'] : null,
            frequency: isset($validated['recurrence_frequency']) ? RecurrenceFrequency::from((string) $validated['recurrence_frequency']) : null,
            recurrenceInterval: isset($validated['recurrence_interval']) ? (int) $validated['recurrence_interval'] : null,
            recurrenceUntilLocal: isset($validated['recurrence_until_local'])
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
            recurrenceUntilLocal: isset($validated['recurrence_until_local'])
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
            firstLocalStart: $this->requiredTime((string) $validated['first_local_start'], $record->timezone, 'first_local_start'),
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

    private function requiredTime(string $value, string $timezone, string $field): CarbonImmutable
    {
        $time = CarbonImmutable::createFromFormat('Y-m-d\\TH:i', $value, $timezone);
        if (! $time instanceof CarbonImmutable) {
            throw ValidationException::withMessages([$field => 'A valid local date and time is required.']);
        }

        return $time;
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
