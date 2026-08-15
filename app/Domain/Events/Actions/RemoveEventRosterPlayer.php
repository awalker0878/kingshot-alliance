<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class RemoveEventRosterPlayer
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventCapabilityGuard $capabilities,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventRosterMember $member): EventRosterMember
    {
        $member->loadMissing('roster.occurrence.event');
        $roster = $member->roster;
        $occurrence = $roster->occurrence;
        $event = $occurrence->event;

        return DB::transaction(function () use ($actor, $member, $roster, $occurrence, $event): EventRosterMember {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Rosters);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $locked = EventRosterMember::query()
                ->whereKey($member->id)
                ->where('roster_id', $roster->id)
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $lockedOccurrence->id))
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === EventRosterMemberStatus::Removed) {
                return $locked;
            }

            $locked->forceFill([
                'status' => EventRosterMemberStatus::Removed,
                'slot_number' => null,
                'removed_by_player_id' => $context->actor->id,
                'removed_at' => now(),
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'roster_id' => (string) $locked->roster_id,
                'player_id' => (string) $locked->player_id,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record('event.roster.player_removed', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.roster.player_removed',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $locked->refresh();
        });
    }
}
