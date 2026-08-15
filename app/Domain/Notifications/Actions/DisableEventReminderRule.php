<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DisableEventReminderRule
{
    public function __construct(
        private EventMutationAuthority $mutations,
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
