<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Actions;

use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class CancelEvent
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $eventId): void
    {
        DB::transaction(function () use ($actorPlayerId, $eventId): void {
            $context = $this->eventWriteState->lockEventScope($actorPlayerId, $eventId, true);
            $this->mutations->authorizeManager($context);

            $context->event->forceFill([
                'status' => EventStatus::Cancelled,
                'updated_by_player_id' => $context->actor->playerId,
            ])->save();

            $context->event->occurrences()
                ->where('starts_at', '>=', now())
                ->update([
                    'status' => EventOccurrenceStatus::Cancelled->value,
                    'updated_at' => now(),
                ]);

            $metadata = [
                'scope' => $context->target->scope->value,
                'target_id' => $context->target->targetId,
                'actor_player_id' => $context->actor->playerId,
            ];

            $this->audit->record('event.cancelled', $context->actor, $context->event, metadata: $metadata);
            $this->outbox->record(
                'event.cancelled',
                $context->target->allianceId,
                $context->event,
                $metadata,
                partitionKey: $context->target->partitionKey(),
            );
        });
    }
}
