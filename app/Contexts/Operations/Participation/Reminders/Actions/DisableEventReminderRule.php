<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Actions;

use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DisableEventReminderRule
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $eventId, string $ruleId): void
    {
        DB::transaction(function () use ($actorPlayerId, $eventId, $ruleId): void {
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, $eventId);
            $this->authorization->authorizeManager($context);
            $rule = EventReminderRule::query()
                ->whereKey($ruleId)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $rule->is_enabled) {
                return;
            }

            $rule->forceFill(['is_enabled' => false, 'updated_by_player_id' => $actorPlayerId])->save();
            $metadata = ['event_id' => (string) $context->event->id, 'actor_player_id' => $actorPlayerId];
            $this->audit->record('event.reminder.rule.disabled', $context->actor, $rule, $context->target->allianceId, $metadata);
            $this->outbox->record('event.reminder.rule.disabled', $context->target->allianceId, $rule, $metadata, partitionKey: $context->target->partitionKey());
        });
    }
}
