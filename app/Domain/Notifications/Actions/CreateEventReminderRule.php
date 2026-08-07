<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventOutbox;
use App\Domain\Identity\Models\User;
use App\Domain\Notifications\Models\EventReminderRule;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CreateEventReminderRule
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        Event $event,
        int $minutesBeforeStart,
        string $channel = 'in_app',
    ): EventReminderRule {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to manage event reminders.');
        }

        if ($event->alliance_id !== $alliance->id) {
            throw new AuthorizationException('The event does not belong to the active alliance.');
        }

        if ($minutesBeforeStart < 1 || $minutesBeforeStart > 10080) {
            throw new InvalidArgumentException('Reminder lead time must be between 1 minute and 7 days.');
        }

        if ($channel !== 'in_app') {
            throw new InvalidArgumentException('Phase 3 supports the in-app reminder channel.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $event,
            $minutesBeforeStart,
            $channel,
        ): EventReminderRule {
            $rule = EventReminderRule::query()->create([
                'alliance_id' => $alliance->id,
                'event_id' => $event->id,
                'minutes_before_start' => $minutesBeforeStart,
                'channel' => $channel,
                'is_enabled' => true,
            ]);

            $this->audit->record('event.reminder.rule.created', $actor, $rule, $alliance, [
                'event_id' => $event->id,
                'minutes_before_start' => $minutesBeforeStart,
                'channel' => $channel,
            ]);
            $this->outbox->record('event.reminder.rule.created', $alliance, $rule, [
                'event_id' => $event->id,
                'minutes_before_start' => $minutesBeforeStart,
                'channel' => $channel,
            ]);

            return $rule;
        });
    }
}
