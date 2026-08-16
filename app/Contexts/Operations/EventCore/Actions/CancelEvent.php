<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Actions;

use App\Contexts\Operations\EventCore\Services\EventWriteState;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\EventCore\Enums\EventStatus;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class CancelEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Event $event): Event
    {
        return DB::transaction(function () use ($actor, $event): Event {
            $context = $this->eventWriteState->lockEventScope($actor, $event, true);
            $this->mutations->authorizeManager($context);
            $locked = $context->event;
            $target = $context->target;
            $currentActor = $context->actor;

            $locked->forceFill([
                'status' => EventStatus::Cancelled,
                'updated_by_player_id' => $currentActor->id,
            ])->save();

            $locked->occurrences()
                ->where('starts_at', '>=', now())
                ->update([
                    'status' => EventOccurrenceStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $locked->scope->value,
                'target_id' => (string) $target->id,
                'actor_player_id' => (string) $currentActor->id,
            ];

            $this->audit->record('event.cancelled', $currentActor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.cancelled',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $locked->scope->value.':'.$target->id,
            );

            return $locked->refresh()->load('occurrences');
        });
    }
}
