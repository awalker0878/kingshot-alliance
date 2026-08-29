<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateEventReminderRule
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private EventWorkflowGuard $workflows,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $eventId,
        int $minutesBefore,
        EventReminderAudience $audience,
        string $channel = 'in_app',
        EventReminderTrigger $trigger = EventReminderTrigger::BeforeStart,
        ?string $pollId = null,
    ): void {
        if ($minutesBefore < 1 || $minutesBefore > 10080) {
            throw ValidationException::withMessages(['minutes_before' => 'Reminder lead time must be between 1 minute and 7 days.']);
        }
        if ($channel !== 'in_app') {
            throw ValidationException::withMessages(['channel' => 'Only in-app Event reminders are currently supported.']);
        }

        DB::transaction(function () use ($actorPlayerId, $eventId, $minutesBefore, $audience, $channel, $trigger, $pollId): void {
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, $eventId);
            $this->authorization->authorizeManager($context);
            $event = $context->event;

            if ($audience === EventReminderAudience::Target && $event->scopeEnum() !== EventScope::Player) {
                throw ValidationException::withMessages(['audience' => 'Target reminders are available only for Player-scoped Events.']);
            }
            if ($audience === EventReminderAudience::Responded
                && ! $this->workflows->supports($event, EventWorkflowDimension::Participation)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not collect responses.']);
            }
            if ($audience === EventReminderAudience::Registered
                && ! $this->workflows->supports($event, EventWorkflowDimension::Participation)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not use registration.']);
            }
            if ($audience === EventReminderAudience::Rostered
                && ! $this->workflows->supports($event, EventWorkflowDimension::Roster)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not use rosters.']);
            }

            $poll = null;
            if ($trigger === EventReminderTrigger::BeforeStart) {
                if ($pollId !== null) {
                    throw ValidationException::withMessages(['poll' => 'Event-start reminders cannot reference a poll.']);
                }
            } else {
                if ($pollId === null || $pollId === '') {
                    throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a poll.']);
                }

                $poll = EventPoll::query()
                    ->whereKey($pollId)
                    ->whereHas('occurrence', static fn ($query) => $query->where('event_id', $event->id))
                    ->sharedLock()
                    ->firstOrFail();
                if ($poll->closes_at === null) {
                    throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a closing poll from this Event.']);
                }
            }

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
                    'created_by_player_id' => $actorPlayerId,
                    'updated_by_player_id' => $actorPlayerId,
                ],
            );

            $eventName = 'event.reminder.rule.created';
            if (! $rule->wasRecentlyCreated) {
                $rule = EventReminderRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
                if ($rule->is_enabled) {
                    return;
                }

                $rule->forceFill(['is_enabled' => true, 'updated_by_player_id' => $actorPlayerId])->save();
                $eventName = 'event.reminder.rule.enabled';
            }

            $metadata = [
                'event_id' => (string) $event->id,
                'poll_id' => $poll?->id,
                'trigger_type' => $trigger->value,
                'minutes_before' => $minutesBefore,
                'audience' => $audience->value,
                'channel' => $channel,
                'actor_player_id' => $actorPlayerId,
            ];
            $this->audit->record($eventName, $context->actor, $rule, $context->target->allianceId, $metadata);
            $this->outbox->record($eventName, $context->target->allianceId, $rule, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
