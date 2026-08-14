<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventRosterPlayer
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventRosterMember $member): EventRosterMember
    {
        $member->loadMissing('roster.occurrence.event.typeScope');
        $event = $member->roster->occurrence->event;
        $this->capabilities->require($event, EventCapability::Rosters);
        $this->authorization->authorizeManager($actor, $event);
        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $member, $event, $target): EventRosterMember {
            $locked = EventRosterMember::query()->whereKey($member->id)->lockForUpdate()->firstOrFail();
            if ($locked->status === EventRosterMemberStatus::Removed) {
                return $locked;
            }
            $locked->forceFill([
                'status' => EventRosterMemberStatus::Removed,
                'slot_number' => null,
                'removed_by_player_id' => $actor->id,
                'removed_at' => now(),
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'roster_id' => (string) $locked->roster_id,
                'player_id' => (string) $locked->player_id,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.roster.player_removed', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('event.roster.player_removed', $alliance?->id, $locked, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $locked->refresh();
        });
    }
}
