<?php

declare(strict_types=1);

namespace App\Contexts\Platform\EventAdministration\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Operations\EventCore\Actions\PersistEventTypeScopeConfiguration;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\EventCore\Enums\EventScheduleSource;
use App\Contexts\Operations\EventCore\Enums\RecurrenceFrequency;
use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Platform\Access\Services\PlatformMutationAuthority;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class UpdateEventTypeScope
{
    public function __construct(
        private PlatformMutationAuthority $platformMutations,
        private PersistEventTypeScopeConfiguration $persistConfiguration,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<EventCapability>  $capabilities
     * @param  array<string, mixed>  $defaultSettings
     */
    public function handle(
        User $actor,
        EventTypeScope $configuration,
        bool $isActive,
        ?int $defaultDurationMinutes,
        ?int $defaultCapacity,
        EventScheduleSource $scheduleSource,
        EventRecurrencePolicy $recurrencePolicy,
        RecurrenceFrequency $defaultRecurrenceFrequency,
        int $defaultRecurrenceInterval,
        ?int $minimumRepeatIntervalMinutes,
        ?int $defaultRegistrationOpensMinutesBefore,
        ?int $defaultRegistrationClosesMinutesBefore,
        ?string $defaultInstructionsKey,
        array $defaultSettings,
        array $capabilities,
    ): EventTypeScope {
        return DB::transaction(function () use (
            $actor,
            $configuration,
            $isActive,
            $defaultDurationMinutes,
            $defaultCapacity,
            $scheduleSource,
            $recurrencePolicy,
            $defaultRecurrenceFrequency,
            $defaultRecurrenceInterval,
            $minimumRepeatIntervalMinutes,
            $defaultRegistrationOpensMinutesBefore,
            $defaultRegistrationClosesMinutesBefore,
            $defaultInstructionsKey,
            $defaultSettings,
            $capabilities,
        ): EventTypeScope {
            $context = $this->platformMutations->require($actor);
            $updated = $this->persistConfiguration->handle(
                configuration: $configuration,
                isActive: $isActive,
                defaultDurationMinutes: $defaultDurationMinutes,
                defaultCapacity: $defaultCapacity,
                scheduleSource: $scheduleSource,
                recurrencePolicy: $recurrencePolicy,
                defaultRecurrenceFrequency: $defaultRecurrenceFrequency,
                defaultRecurrenceInterval: $defaultRecurrenceInterval,
                minimumRepeatIntervalMinutes: $minimumRepeatIntervalMinutes,
                defaultRegistrationOpensMinutesBefore: $defaultRegistrationOpensMinutesBefore,
                defaultRegistrationClosesMinutesBefore: $defaultRegistrationClosesMinutesBefore,
                defaultInstructionsKey: $defaultInstructionsKey,
                defaultSettings: $defaultSettings,
                capabilities: $capabilities,
            );

            $wanted = array_values(array_unique(array_map(
                static fn (EventCapability $capability): string => $capability->value,
                $capabilities,
            )));
            $eventType = $updated->eventType;
            $metadata = [
                'event_type_id' => $eventType->id,
                'scope' => $updated->scopeEnum()->value,
                'is_active' => $updated->is_active,
                'schedule_source' => $updated->scheduleSourceEnum()->value,
                'recurrence_policy' => $updated->recurrencePolicyEnum()->value,
                'capabilities' => $wanted,
            ];

            $this->audit->record(
                event: 'event-type.scope.updated',
                actor: $context->actor,
                subject: $updated,
                metadata: $metadata,
            );
            $this->outbox->record('event-type.scope.updated', null, $updated, $metadata);

            return $updated;
        });
    }
}
