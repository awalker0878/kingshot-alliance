<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class CancelEvent
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Event $event): Event
    {
        return DB::transaction(function () use ($actor, $event): Event {
            $context = $this->mutations->requireManagerExclusive($actor, $event);
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
