<?php

declare(strict_types=1);

namespace App\Contexts\Platform\EventAdministration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Operations\Events\Actions\PersistEventTypeScopeConfiguration;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use Illuminate\Support\Facades\DB;

final class UpdateEventTypeScope
{
    public function __construct(
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $platformMutations,
        private PersistEventTypeScopeConfiguration $persistConfiguration,
    ) {}

    /**
     * @param list<EventCapability> $capabilities
     * @param array<string, mixed> $defaultSettings
     */
    public function handle(
        AccountIdentity $actor,
        string $configurationId,
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
    ): void {
        DB::transaction(function () use (
            $actor,
            $configurationId,
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
        ): void {
            $context = $this->platformMutations->authorizeContext($this->platformWriteState->lock($actor));

            $this->persistConfiguration->handle(
                actor: $context->actor,
                configurationId: $configurationId,
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
        });
    }
}
