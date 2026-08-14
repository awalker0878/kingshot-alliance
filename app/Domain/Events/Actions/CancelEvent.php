<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final class CancelEvent
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Event $event): Event
    {
        $event->loadMissing('typeScope');
        $target = $this->targets->forEvent($event);
        $this->authorization->authorize(
            $actor,
            $event->scope,
            $target,
            PermissionKey::from((string) $event->typeScope->manage_permission_key),
        );

        return DB::transaction(function () use ($actor, $event, $target): Event {
            $locked = Event::query()->whereKey($event->id)->lockForUpdate()->firstOrFail();
            $locked->forceFill([
                'status' => EventStatus::Cancelled,
                'updated_by_player_id' => $actor->id,
            ])->save();
            $locked->occurrences()
                ->where('starts_at', '>=', now())
                ->update(['status' => EventOccurrenceStatus::Cancelled->value, 'updated_at' => now()]);

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'scope' => $locked->scope->value,
                'target_id' => (string) $target->id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.cancelled', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('event.cancelled', $alliance?->id, $locked, $metadata, partitionKey: $locked->scope->value.':'.$target->id);

            return $locked->refresh()->load('occurrences');
        });
    }
}
