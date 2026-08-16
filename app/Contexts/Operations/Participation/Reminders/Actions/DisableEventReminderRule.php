<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DisableEventReminderRule
{
    public function __construct(
        private EventAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Event $event, EventReminderRule $rule): EventReminderRule
    {
        return DB::transaction(function () use ($actor, $event, $rule): EventReminderRule {
            $context = $this->mutations->requireManager($actor, $event);
            $locked = EventReminderRule::query()
                ->whereKey($rule->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_enabled) {
                return $locked;
            }

            $locked->forceFill([
                'is_enabled' => false,
                'updated_by_player_id' => $context->actor->id,
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record('event.reminder.rule.disabled', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.reminder.rule.disabled',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $locked->refresh();
        });
    }
}
