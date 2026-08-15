<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventReminderTrigger;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventPoll;
use App\Domain\Events\Services\EventCapabilityResolver;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateEventReminderRule
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityResolver $capabilities,
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
        if ($minutesBefore < 1 || $minutesBefore > 10080) {
            throw ValidationException::withMessages(['minutes_before' => 'Reminder lead time must be between 1 minute and 7 days.']);
        }
        if ($channel !== 'in_app') {
            throw ValidationException::withMessages(['channel' => 'Only in-app Event reminders are currently supported.']);
        }

        return DB::transaction(function () use ($actor, $event, $minutesBefore, $audience, $channel, $trigger, $poll): EventReminderRule {
            $context = $this->mutations->requireManager($actor, $event);
            $currentEvent = $context->event;

            if ($audience === EventReminderAudience::Target && $currentEvent->scope !== EventScope::Player) {
                throw ValidationException::withMessages(['audience' => 'Target reminders are available only for Player-scoped Events.']);
            }
            if ($audience === EventReminderAudience::Responded
                && ! $this->capabilities->supports($context->typeScope, EventCapability::Responses)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not collect responses.']);
            }
            if ($audience === EventReminderAudience::Registered
                && ! $this->capabilities->supports($context->typeScope, EventCapability::Registration)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not use registration.']);
            }
            if ($audience === EventReminderAudience::Rostered
                && ! $this->capabilities->supports($context->typeScope, EventCapability::Rosters)) {
                throw ValidationException::withMessages(['audience' => 'This Event does not use rosters.']);
            }

            $currentPoll = null;
            if ($trigger === EventReminderTrigger::BeforeStart) {
                if ($poll !== null) {
                    throw ValidationException::withMessages(['poll' => 'Event-start reminders cannot reference a poll.']);
                }
            } else {
                if (! $poll instanceof EventPoll) {
                    throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a poll.']);
                }

                $currentPoll = EventPoll::query()
                    ->whereKey($poll->id)
                    ->whereHas('occurrence', static fn ($query) => $query->where('event_id', $currentEvent->id))
                    ->sharedLock()
                    ->firstOrFail();
                if ($currentPoll->closes_at === null) {
                    throw ValidationException::withMessages(['poll' => 'Poll-close reminders require a closing poll from this Event.']);
                }
            }

            // Rule-definition uniqueness is enforced by partial unique indexes for Event
            // and Poll reminder definitions. The rule row is the Notifications aggregate.
            $rule = EventReminderRule::query()->firstOrCreate(
                [
                    'event_id' => $currentEvent->id,
                    'poll_id' => $currentPoll?->id,
                    'trigger_type' => $trigger->value,
                    'minutes_before' => $minutesBefore,
                    'audience' => $audience->value,
                    'channel' => $channel,
                ],
                [
                    'is_enabled' => true,
                    'created_by_player_id' => $context->actor->id,
                    'updated_by_player_id' => $context->actor->id,
                ],
            );

            $eventName = 'event.reminder.rule.created';
            if (! $rule->wasRecentlyCreated) {
                $rule = EventReminderRule::query()->whereKey($rule->id)->lockForUpdate()->firstOrFail();
                if ($rule->is_enabled) {
                    return $rule;
                }

                $rule->forceFill([
                    'is_enabled' => true,
                    'updated_by_player_id' => $context->actor->id,
                ])->save();
                $eventName = 'event.reminder.rule.enabled';
            }

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $currentEvent->id,
                'poll_id' => $currentPoll?->id,
                'trigger_type' => $trigger->value,
                'minutes_before' => $minutesBefore,
                'audience' => $audience->value,
                'channel' => $channel,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record($eventName, $context->actor, $rule, $alliance, $metadata);
            $this->outbox->record(
                $eventName,
                $alliance?->id,
                $rule,
                $metadata,
                partitionKey: $currentEvent->scope->value.':'.$context->target->id,
            );

            return $rule->refresh();
        });
    }
}
