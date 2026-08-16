<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRoster;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondToEventRosterAssignment
{
    public function __construct(
        private EventAuthorization $mutations,
        private EventCapabilityGuard $capabilities,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, EventRosterMember $member, Player $player, EventRosterMemberStatus $status): EventRosterMember
    {
        if (! in_array($status, [EventRosterMemberStatus::Confirmed, EventRosterMemberStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Roster assignment response must be confirmed or declined.']);
        }

        $roster = $member->roster()->firstOrFail();
        $occurrence = $roster->occurrence()->firstOrFail();
        $event = $occurrence->event()->firstOrFail();

        return DB::transaction(function () use ($actor, $member, $player, $status, $roster, $occurrence, $event): EventRosterMember {
            $context = $this->mutations->requireSelf($actor, $event, $player);
            $this->capabilities->require($context->event, EventCapability::Rosters);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();
            $lockedRoster = EventRoster::query()
                ->whereKey($roster->id)
                ->where('occurrence_id', $lockedOccurrence->id)
                ->lockForUpdate()
                ->firstOrFail();
            $locked = EventRosterMember::query()
                ->whereKey($member->id)
                ->where('roster_id', $lockedRoster->id)
                ->where('player_id', $context->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ((string) $locked->player_id !== (string) $context->actor->id) {
                throw new AuthorizationException;
            }
            if (in_array($locked->statusEnum(), [EventRosterMemberStatus::Removed, EventRosterMemberStatus::Participated, EventRosterMemberStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This roster assignment can no longer be confirmed or declined.']);
            }

            if ($status === EventRosterMemberStatus::Confirmed && ! $locked->statusEnum()->occupiesSlot()) {
                $occupying = $this->occupyingStatuses();
                $activeCount = EventRosterMember::query()
                    ->where('roster_id', $lockedRoster->id)
                    ->whereIn('status', $occupying)
                    ->count();
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
                    ->where('player_id', $context->actor->id)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->whereHas('roster', static fn ($query) => $query
                        ->where('occurrence_id', $lockedOccurrence->id)
                        ->where('assignment_group', $lockedRoster->assignment_group))
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active assignment in the same roster group.']);
                }
            }

            if ($status === EventRosterMemberStatus::Confirmed) {
                $representedAlliance = $locked->alliance_id === null
                    ? null
                    : Alliance::query()->whereKey($locked->alliance_id)->first();
                $this->contexts->freeze($lockedOccurrence, $context->actor, $representedAlliance);
            }

            $locked->forceFill([
                'status' => $status,
                'responded_by_player_id' => $context->actor->id,
                'responded_at' => now(),
            ])->save();

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedOccurrence->id,
                'roster_id' => (string) $lockedRoster->id,
                'player_id' => (string) $context->actor->id,
                'status' => $status->value,
            ];
            $this->audit->record('event.roster.assignment_responded', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'event.roster.assignment_responded',
                $alliance?->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

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
