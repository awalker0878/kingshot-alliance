<?php

declare(strict_types=1);

namespace App\Domain\Events\Http\Controllers;

use App\Domain\Events\Actions\UpdateEventTypeScope;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Models\EventTypeScope;
use App\Contexts\Accounts\Models\User;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

final class EventTypeAdministrationController extends Controller
{
    public function index(Request $request): Response
    {
        $types = EventType::query()
            ->with(['scopes.capabilities'])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return Inertia::render('Platform/EventTypes', [
            'user' => $this->identity($request),
            'eventTypes' => $types->map(static fn (EventType $type): array => [
                'id' => (string) $type->id,
                'slug' => $type->slug,
                'nameKey' => $type->name_key,
                'descriptionKey' => $type->description_key,
                'category' => $type->category->value,
                'iconKey' => $type->icon_key,
                'active' => (bool) $type->is_active,
                'system' => (bool) $type->is_system,
                'scopes' => $type->scopes
                    ->sortBy('sort_order')
                    ->values()
                    ->map(static fn (EventTypeScope $scope): array => [
                        'id' => (string) $scope->id,
                        'scope' => $scope->scope->value,
                        'active' => (bool) $scope->is_active,
                        'defaultDurationMinutes' => $scope->default_duration_minutes,
                        'defaultCapacity' => $scope->default_capacity,
                        'scheduleSource' => $scope->schedule_source->value,
                        'recurrencePolicy' => $scope->recurrence_policy->value,
                        'defaultRecurrenceFrequency' => $scope->default_recurrence_frequency->value,
                        'defaultRecurrenceInterval' => $scope->default_recurrence_interval,
                        'minimumRepeatIntervalMinutes' => $scope->minimum_repeat_interval_minutes,
                        'defaultRegistrationOpensMinutesBefore' => $scope->default_registration_opens_minutes_before,
                        'defaultRegistrationClosesMinutesBefore' => $scope->default_registration_closes_minutes_before,
                        'defaultInstructionsKey' => $scope->default_instructions_key,
                        'defaultSettings' => $scope->default_settings ?? [],
                        'capabilities' => $scope->capabilities
                            ->pluck('capability')
                            ->map(static fn ($value): string => $value instanceof EventCapability ? $value->value : (string) $value)
                            ->sort()
                            ->values()
                            ->all(),
                    ])->all(),
            ])->all(),
            'capabilityOptions' => array_map(
                static fn (EventCapability $capability): string => $capability->value,
                EventCapability::cases(),
            ),
            'scopeOptions' => array_map(static fn (EventScope $scope): string => $scope->value, EventScope::cases()),
            'scheduleSourceOptions' => array_map(static fn (EventScheduleSource $source): string => $source->value, EventScheduleSource::cases()),
            'recurrencePolicyOptions' => array_map(static fn (EventRecurrencePolicy $policy): string => $policy->value, EventRecurrencePolicy::cases()),
            'recurrenceFrequencyOptions' => array_map(static fn (RecurrenceFrequency $frequency): string => $frequency->value, RecurrenceFrequency::cases()),
            'status' => $request->session()->get('status'),
        ]);
    }

    public function update(
        Request $request,
        EventType $eventType,
        string $scope,
        UpdateEventTypeScope $update,
    ): RedirectResponse {
        $eventScope = EventScope::tryFrom($scope);
        abort_unless($eventScope instanceof EventScope, 404);

        $configuration = $eventType->scopes()
            ->where('scope', $eventScope->value)
            ->firstOrFail();

        $validated = $request->validate([
            'is_active' => ['required', 'boolean'],
            'default_duration_minutes' => ['nullable', 'integer', 'between:1,10080'],
            'default_capacity' => ['nullable', 'integer', 'between:1,100000'],
            'schedule_source' => ['required', Rule::enum(EventScheduleSource::class)],
            'recurrence_policy' => ['required', Rule::enum(EventRecurrencePolicy::class)],
            'default_recurrence_frequency' => ['required', Rule::enum(RecurrenceFrequency::class)],
            'default_recurrence_interval' => ['required', 'integer', 'between:1,365'],
            'minimum_repeat_interval_minutes' => ['nullable', 'integer', 'between:1,525600'],
            'default_registration_opens_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'default_registration_closes_minutes_before' => ['nullable', 'integer', 'between:0,525600'],
            'default_instructions_key' => ['nullable', 'string', 'max:180'],
            'default_settings_json' => ['nullable', 'string', 'max:20000'],
            'capabilities' => ['array'],
            'capabilities.*' => [Rule::enum(EventCapability::class)],
        ]);

        $defaultSettings = [];
        if (isset($validated['default_settings_json']) && trim((string) $validated['default_settings_json']) !== '') {
            try {
                $decoded = json_decode((string) $validated['default_settings_json'], true, 64, JSON_THROW_ON_ERROR);
            } catch (\JsonException) {
                throw ValidationException::withMessages([
                    'default_settings_json' => 'Default settings must contain valid JSON.',
                ]);
            }

            if (! is_array($decoded)) {
                throw ValidationException::withMessages([
                    'default_settings_json' => 'Default settings must decode to a JSON object or array.',
                ]);
            }

            $defaultSettings = $decoded;
        }

        $update->handle(
            actor: $this->user($request),
            configuration: $configuration,
            isActive: (bool) $validated['is_active'],
            defaultDurationMinutes: isset($validated['default_duration_minutes']) ? (int) $validated['default_duration_minutes'] : null,
            defaultCapacity: isset($validated['default_capacity']) ? (int) $validated['default_capacity'] : null,
            scheduleSource: EventScheduleSource::from((string) $validated['schedule_source']),
            recurrencePolicy: EventRecurrencePolicy::from((string) $validated['recurrence_policy']),
            defaultRecurrenceFrequency: RecurrenceFrequency::from((string) $validated['default_recurrence_frequency']),
            defaultRecurrenceInterval: (int) $validated['default_recurrence_interval'],
            minimumRepeatIntervalMinutes: isset($validated['minimum_repeat_interval_minutes']) ? (int) $validated['minimum_repeat_interval_minutes'] : null,
            defaultRegistrationOpensMinutesBefore: isset($validated['default_registration_opens_minutes_before']) ? (int) $validated['default_registration_opens_minutes_before'] : null,
            defaultRegistrationClosesMinutesBefore: isset($validated['default_registration_closes_minutes_before']) ? (int) $validated['default_registration_closes_minutes_before'] : null,
            defaultInstructionsKey: isset($validated['default_instructions_key']) ? (string) $validated['default_instructions_key'] : null,
            defaultSettings: $defaultSettings,
            capabilities: array_map(
                static fn (string $value): EventCapability => EventCapability::from($value),
                $validated['capabilities'] ?? [],
            ),
        );

        return back()->with('status', 'event-type-scope-updated');
    }

    /** @return array{name:string,email:string} */
    private function identity(Request $request): array
    {
        $user = $this->user($request);

        return [
            'name' => (string) $user->name,
            'email' => (string) $user->email,
        ];
    }

    private function user(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        return $user;
    }
}
