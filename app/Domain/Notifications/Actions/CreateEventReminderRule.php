<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventReminderTrigger;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventPoll;
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateEventReminderRule
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityResolver $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Event $event,
        int $minutesBefore,
        EventReminderAudience $audience,
        string $channel = 'in_app',
        EventReminderTrigger $trigger = EventReminderTrigger::BeforeStart,
        ?EventPoll $poll = null,
    ): EventReminderRule {
        $event->loadMissing('typeScope');
        $this->authorization->authorizeManager($actor, $event);

        if ($minutesBefore < 1 || $minutesBefore > 10080) {
            throw ValidationException::withMessages(['minutes_before' => 'Reminder lead time must be between 1 minute and 7 days.']);
        }
        if ($channel !== 'in_app') {
            throw ValidationException::withMessages(['channel' => 'Only in-app Event reminders are currently supported.']);
        }
        if ($audience === EventReminderAudience::Target && $event->scope !== EventScope::Player) {
            throw ValidationException::withMessages(['audience' => 'Target reminders are available only for Player-scoped Events.']);
        }
        if ($audience === EventReminderAudience::Responded && ! $this->capabilities->supports($event->typeScope, EventCapability::Responses)) {
            throw ValidationException::withMessages(['audience' => 'This Event does not collect responses.']);
        }
        if ($audience === EventReminderAudience::Registered && ! $this->capabilities->supports($event->typeScope, EventCapability::Registration)) {
            throw ValidationException::withMessages(['audience' => 'This Event does not use registration.']);
        }
        if ($audience === EventReminderAudience::Rostered && ! $this->capabilities->supports($event->typeScope, EventCapability::Rosters)) {
            throw ValidationException::withMessages(['audience' => 'This Event does not use rosters.']);
        }
        if ($trigger === EventReminderTrigger::BeforeStart && $poll !== null) {
            throw ValidationException::withMessages(['poll' => 'Event-start reminders cannot reference a poll.']);
        }
        if ($trigger === EventReminderTrigger::BeforePollClose) {
            if (! $poll instanceof EventPoll) {
                throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a poll.']);
            }
            $poll->loadMissing('occurrence');
            if ((string) $poll->occurrence->event_id !== (string) $event->id || $poll->closes_at === null) {
                throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a closing poll from this Event.']);
            }
        }

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $event, $minutesBefore, $audience, $channel, $trigger, $poll, $target): EventReminderRule {
            $rule = EventReminderRule::query()->firstOrCreate(
                [
                    'event_id' => $event->id,
                    'poll_id' => $poll?->id,
                    'trigger_type' => $trigger->value,
                    'minutes_before' => $minutesBefore,
                    'audience' => $audience->value,
                    'channel' => $channel,
                ],
                [
                    'is_enabled' => true,
                    'created_by_player_id' => $actor->id,
                    'updated_by_player_id' => $actor->id,
                ],
            );

            $eventName = 'event.reminder.rule.created';
            if (! $rule->wasRecentlyCreated) {
                if ($rule->is_enabled) {
                    return $rule;
                }

                $rule->forceFill([
                    'is_enabled' => true,
                    'updated_by_player_id' => $actor->id,
                ])->save();
                $eventName = 'event.reminder.rule.enabled';
            }

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'poll_id' => $poll?->id,
                'trigger_type' => $trigger->value,
                'minutes_before' => $minutesBefore,
                'audience' => $audience->value,
                'channel' => $channel,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record($eventName, $actor, $rule, $alliance, $metadata);
            $this->outbox->record($eventName, $alliance?->id, $rule, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $rule->refresh();
        });
    }
}
