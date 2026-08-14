<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Notifications\Models\EventReminderRule;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class DisableEventReminderRule
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Event $event, EventReminderRule $rule): EventReminderRule
    {
        $this->authorization->authorizeManager($actor, $event);
        abort_unless((string) $rule->event_id === (string) $event->id, 404);

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $event, $rule, $target): EventReminderRule {
            $locked = EventReminderRule::query()
                ->whereKey($rule->id)
                ->where('event_id', $event->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $locked->is_enabled) {
                return $locked;
            }

            $locked->forceFill([
                'is_enabled' => false,
                'updated_by_player_id' => $actor->id,
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.reminder.rule.disabled', $actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.reminder.rule.disabled',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $event->scope->value.':'.$target->id,
            );

            return $locked->refresh();
        });
    }
}
