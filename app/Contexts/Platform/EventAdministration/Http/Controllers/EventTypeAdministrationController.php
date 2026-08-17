<?php

declare(strict_types=1);

namespace App\Contexts\Platform\EventAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Queries\EventTypeScopeReferenceQuery;
use App\Contexts\Platform\EventAdministration\Actions\UpdateEventTypeScope;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

final class EventTypeAdministrationController extends Controller
{
    public function __construct(
        private readonly AccountIdentityQuery $accounts,
        private readonly EventTypeScopeReferenceQuery $configurations,
    ) {}

    public function update(
        Request $request,
        string $eventType,
        string $scope,
        UpdateEventTypeScope $update,
    ): RedirectResponse {
        $eventScope = EventScope::tryFrom($scope);
        abort_unless($eventScope instanceof EventScope, 404);
        $configurationId = $this->configurations->requireConfigurationId($eventType, $eventScope);

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
            actor: $this->account($request),
            configurationId: $configurationId,
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
            capabilities: array_values(array_map(
                static fn (string $value): EventCapability => EventCapability::from($value),
                $validated['capabilities'] ?? [],
            )),
        );

        return back()->with('status', 'event-type-scope-updated');
    }

    private function account(Request $request): AccountIdentity
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);

        return $this->accounts->require((int) $identifier);
    }
}
