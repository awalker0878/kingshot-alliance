<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRoster;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEventRosterAssignment
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventRosterMember $member, Player $player, EventRosterMemberStatus $status): EventRosterMember
    {
        if (! in_array($status, [EventRosterMemberStatus::Confirmed, EventRosterMemberStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Roster assignment response must be confirmed or declined.']);
        }
        $member->loadMissing('roster.occurrence.event.typeScope');
        $roster = $member->roster;
        $occurrence = $roster->occurrence;
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Rosters);
        $this->authorization->authorizeSelf($actor, $event, $player);
        if ((string) $member->player_id !== (string) $player->id) {
            throw new AuthorizationException;
        }

        $target = $this->targets->forEvent($event);
        $occupying = $this->occupyingStatuses();

        return DB::transaction(function () use ($actor, $member, $player, $status, $roster, $occurrence, $event, $target, $occupying): EventRosterMember {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $lockedRoster = EventRoster::query()->whereKey($roster->id)->lockForUpdate()->firstOrFail();
            $locked = EventRosterMember::query()->whereKey($member->id)->where('player_id', $player->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [EventRosterMemberStatus::Removed, EventRosterMemberStatus::Participated, EventRosterMemberStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This roster assignment can no longer be confirmed or declined.']);
            }

            if ($status === EventRosterMemberStatus::Confirmed && ! $locked->status->occupiesSlot()) {
                $activeCount = EventRosterMember::query()->where('roster_id', $lockedRoster->id)->whereIn('status', $occupying)->count();
                if ($lockedRoster->capacity !== null && $activeCount >= (int) $lockedRoster->capacity) {
                    throw ValidationException::withMessages(['status' => 'This roster is now at capacity.']);
                }
                if ($locked->slot_number !== null && EventRosterMember::query()
                    ->where('roster_id', $lockedRoster->id)
                    ->where('slot_number', $locked->slot_number)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This roster slot has been reassigned.']);
                }
                if (EventRosterMember::query()
                    ->where('player_id', $player->id)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->whereHas('roster', static fn ($query) => $query
                        ->where('occurrence_id', $occurrence->id)
                        ->where('assignment_group', $lockedRoster->assignment_group))
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active assignment in the same roster group.']);
                }
            }

            $locked->forceFill([
                'status' => $status,
                'responded_by_player_id' => $player->id,
                'responded_at' => now(),
            ])->save();

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'roster_id' => (string) $lockedRoster->id,
                'player_id' => (string) $player->id,
                'status' => $status->value,
            ];
            $this->audit->record('event.roster.assignment_responded', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('event.roster.assignment_responded', $alliance?->id, $locked, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $locked->refresh()->load(['player', 'roster']);
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_values(array_map(
            static fn (EventRosterMemberStatus $status): string => $status->value,
            array_filter(EventRosterMemberStatus::cases(), static fn (EventRosterMemberStatus $status): bool => $status->occupiesSlot()),
        ));
    }
}
