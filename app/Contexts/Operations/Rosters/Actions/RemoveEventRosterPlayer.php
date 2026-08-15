<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventRosterMember;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
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
