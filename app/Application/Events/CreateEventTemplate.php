<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AuditRecorder;
use App\Domain\Events\Enums\RecurrenceFrequency;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\EventTemplate;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEventTemplate
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        string $name,
        int $durationMinutes,
        ?int $capacity = null,
        ?int $registrationOpensMinutesBefore = null,
        int $registrationClosesMinutesBefore = 0,
        RecurrenceFrequency $frequency = RecurrenceFrequency::None,
        int $recurrenceInterval = 1,
        ?string $instructions = null,
    ): EventTemplate {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage event templates.');
        }

        if ($durationMinutes < 1 || $durationMinutes > 1440) {
            throw new InvalidArgumentException('Event duration must be between 1 and 1440 minutes.');
        }

        if ($capacity !== null && $capacity < 1) {
            throw new InvalidArgumentException('Event capacity must be at least one when provided.');
        }

        if ($recurrenceInterval < 1) {
            throw new InvalidArgumentException('Recurrence interval must be at least one.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $name,
            $instructions,
            $durationMinutes,
            $capacity,
            $registrationOpensMinutesBefore,
            $registrationClosesMinutesBefore,
            $frequency,
            $recurrenceInterval,
        ): EventTemplate {
            $template = EventTemplate::query()->create([
                'alliance_id' => $alliance->id,
                'name' => trim($name),
                'instructions' => $instructions === null ? null : trim($instructions),
                'timezone' => $alliance->timezone,
                'duration_minutes' => $durationMinutes,
                'capacity' => $capacity,
                'registration_opens_minutes_before' => $registrationOpensMinutesBefore,
                'registration_closes_minutes_before' => $registrationClosesMinutesBefore,
                'recurrence_frequency' => $frequency,
                'recurrence_interval' => $recurrenceInterval,
                'recurrence_weekdays' => null,
                'is_active' => true,
                'created_by_user_id' => $actor->id,
                'updated_by_user_id' => $actor->id,
            ]);

            $this->audit->record('event.template.created', $actor, $template, $alliance, [
                'recurrence' => $frequency->value,
            ]);
            $this->outbox->record('event.template.created', $alliance, $template, [
                'recurrence' => $frequency->value,
            ]);

            return $template;
        });
    }
}
