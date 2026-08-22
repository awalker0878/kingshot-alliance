<?php

declare(strict_types=1);

namespace App\ReadModels\EventTypeAdministration\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Events\Models\EventType;
use App\Contexts\Operations\Events\Models\EventTypeCapability;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final readonly class EventTypeAdministrationReadController
{
    public function __construct(private AccountIdentityQuery $accounts) {}

    public function __invoke(Request $request): Response
    {
        $identifier = $request->user()?->getAuthIdentifier();
        abort_unless(is_numeric($identifier), 401);
        $account = $this->accounts->require((int) $identifier);

        $types = EventType::query()
            ->with(['scopes.capabilities'])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();

        return Inertia::render('Platform/EventTypes/Index', [
            'user' => ['name' => $account->name, 'email' => $account->email],
            'eventTypes' => array_values($types->map(static fn (EventType $type): array => [
                'id' => (string) $type->id,
                'slug' => $type->slug,
                'nameKey' => $type->name_key,
                'descriptionKey' => $type->description_key,
                'category' => $type->categoryEnum()->value,
                'iconKey' => $type->icon_key,
                'active' => (bool) $type->is_active,
                'system' => (bool) $type->is_system,
                'scopes' => array_values($type->scopes
                    ->sortBy('sort_order')
                    ->values()
                    ->map(static fn (EventTypeScope $scope): array => [
                        'id' => (string) $scope->id,
                        'scope' => $scope->scopeEnum()->value,
                        'active' => (bool) $scope->is_active,
                        'defaultDurationMinutes' => $scope->default_duration_minutes,
                        'defaultCapacity' => $scope->default_capacity,
                        'scheduleSource' => $scope->scheduleSourceEnum()->value,
                        'recurrencePolicy' => $scope->recurrencePolicyEnum()->value,
                        'defaultRecurrenceFrequency' => $scope->defaultRecurrenceFrequencyEnum()->value,
                        'defaultRecurrenceInterval' => $scope->default_recurrence_interval,
                        'minimumRepeatIntervalMinutes' => $scope->minimum_repeat_interval_minutes,
                        'defaultRegistrationOpensMinutesBefore' => $scope->default_registration_opens_minutes_before,
                        'defaultRegistrationClosesMinutesBefore' => $scope->default_registration_closes_minutes_before,
                        'defaultInstructionsKey' => $scope->default_instructions_key,
                        'defaultSettings' => $scope->default_settings ?? [],
                        'capabilities' => array_values($scope->capabilities
                            ->map(static fn (EventTypeCapability $capability): string => $capability->capabilityEnum()->value)
                            ->sort()
                            ->values()
                            ->all()),
                    ])->all()),
            ])->all()),
            'capabilityOptions' => array_map(static fn (EventCapability $capability): string => $capability->value, EventCapability::cases()),
            'scopeOptions' => array_map(static fn (EventScope $scope): string => $scope->value, EventScope::cases()),
            'scheduleSourceOptions' => array_map(static fn (EventScheduleSource $source): string => $source->value, EventScheduleSource::cases()),
            'recurrencePolicyOptions' => array_map(static fn (EventRecurrencePolicy $policy): string => $policy->value, EventRecurrencePolicy::cases()),
            'recurrenceFrequencyOptions' => array_map(static fn (RecurrenceFrequency $frequency): string => $frequency->value, RecurrenceFrequency::cases()),
        ]);
    }
}
