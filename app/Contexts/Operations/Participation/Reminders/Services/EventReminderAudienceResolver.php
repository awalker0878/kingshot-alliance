<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Reminders\Services;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\Contexts\Operations\Rosters\Models\EventRosterMember;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class EventReminderAudienceResolver
{
    public function __construct(
        private EventParticipantAuthorization $participants,
        private EventAuthorization $authorization,
        private PlayerReferenceQuery $players,
        private RosterEntryQuery $roster,
    ) {}

    /** @return Collection<int, PlayerReference> */
    public function resolve(EventOccurrence $occurrence, EventReminderAudience $audience): Collection
    {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $playerIds = match ($audience) {
            EventReminderAudience::Target => $this->targetIds($occurrence),
            EventReminderAudience::Responded => EventResponse::query()
                ->where('occurrence_id', $occurrence->id)
                ->whereIn('response', [EventResponseChoice::Going->value, EventResponseChoice::Maybe->value])
                ->pluck('player_id')->map('strval')->all(),
            EventReminderAudience::Registered => EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->pluck('player_id')->map('strval')->all(),
            EventReminderAudience::Rostered => EventRosterMember::query()
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
                ->pluck('player_id')->map('strval')->all(),
            EventReminderAudience::AllScopePlayers => $this->scopePlayerIds($event),
        };

        $references = $this->players->byIds(array_values(array_unique($playerIds)));

        return collect($references)
            ->filter(fn (PlayerReference $player): bool => $player->userId !== null && $this->canReceive($event, $player))
            ->values();
    }

    public function includes(EventOccurrence $occurrence, EventReminderAudience $audience, PlayerReference $player): bool
    {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        if ($player->userId === null || ! $this->canReceive($event, $player)) {
            return false;
        }

        return match ($audience) {
            EventReminderAudience::Target => $event->scopeEnum() === EventScope::Player && (string) $event->player_id === $player->playerId,
            EventReminderAudience::Responded => EventResponse::query()
                ->where('occurrence_id', $occurrence->id)->where('player_id', $player->playerId)
                ->whereIn('response', [EventResponseChoice::Going->value, EventResponseChoice::Maybe->value])->exists(),
            EventReminderAudience::Registered => EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)->where('player_id', $player->playerId)
                ->where('status', EventRegistrationStatus::Registered->value)->exists(),
            EventReminderAudience::Rostered => EventRosterMember::query()
                ->where('player_id', $player->playerId)
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))->exists(),
            EventReminderAudience::AllScopePlayers => $this->participants->eligible($event, $player),
        };
    }

    private function canReceive(Event $event, PlayerReference $player): bool
    {
        if (! $this->participants->eligible($event, $player)) {
            return false;
        }

        $targetId = match ($event->scopeEnum()) {
            EventScope::Player => (string) $event->player_id,
            EventScope::Alliance => (string) $event->alliance_id,
            EventScope::Kingdom => (string) $event->kingdom_id,
        };
        if ($targetId === '') {
            return false;
        }

        $permission = OperationsPermission::from((string) $event->typeScope->view_permission_key);

        return $this->authorization->allows($player->playerId, $event->scopeEnum(), $targetId, $permission);
    }

    /** @return list<string> */
    private function targetIds(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;
        if ($event->scopeEnum() !== EventScope::Player || $event->player_id === null) {
            throw ValidationException::withMessages(['audience' => 'Target reminders are available only for Player-scoped Events.']);
        }

        return [(string) $event->player_id];
    }

    /** @return list<string> */
    private function scopePlayerIds(Event $event): array
    {
        return match ($event->scopeEnum()) {
            EventScope::Player => $event->player_id === null ? [] : [(string) $event->player_id],
            EventScope::Kingdom => array_values(array_map(
                static fn (PlayerReference $player): string => $player->playerId,
                $this->players->inKingdom((string) $event->kingdom_id),
            )),
            EventScope::Alliance => $event->alliance_id === null ? [] : $this->roster->activePlayerIds((string) $event->alliance_id),
        };
    }
}
