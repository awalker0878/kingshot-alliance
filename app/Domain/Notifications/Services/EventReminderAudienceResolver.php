<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Services;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventResponseChoice;
use App\Domain\Events\Enums\EventRosterMemberStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventResponse;
use App\Domain\Events\Models\EventRosterMember;
use App\Domain\Events\Services\EventParticipantAuthorization;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Collection;
use Illuminate\Validation\ValidationException;

final readonly class EventReminderAudienceResolver
{
    public function __construct(private EventParticipantAuthorization $authorization) {}

    /** @return Collection<int, Player> */
    public function resolve(EventOccurrence $occurrence, EventReminderAudience $audience): Collection
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        $playerIds = match ($audience) {
            EventReminderAudience::Target => $this->targetIds($occurrence),
            EventReminderAudience::Responded => EventResponse::query()
                ->where('occurrence_id', $occurrence->id)
                ->whereIn('response', [EventResponseChoice::Going->value, EventResponseChoice::Maybe->value])
                ->pluck('player_id')
                ->all(),
            EventReminderAudience::Registered => EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->pluck('player_id')
                ->all(),
            EventReminderAudience::Rostered => EventRosterMember::query()
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
                ->pluck('player_id')
                ->all(),
            EventReminderAudience::AllScopePlayers => $this->scopePlayerIds($occurrence),
        };

        if ($playerIds === []) {
            return collect();
        }

        return Player::query()
            ->whereIn('id', array_values(array_unique(array_map('strval', $playerIds))))
            ->whereNotNull('user_id')
            ->get()
            ->filter(fn (Player $player): bool => $this->canReceive($event, $player))
            ->values();
    }

    public function includes(EventOccurrence $occurrence, EventReminderAudience $audience, Player $player): bool
    {
        $occurrence->loadMissing('event');
        $event = $occurrence->event;
        if ($player->user_id === null || ! $this->canReceive($event, $player)) {
            return false;
        }

        return match ($audience) {
            EventReminderAudience::Target => $event->scope === EventScope::Player
                && (string) $event->player_id === (string) $player->id,
            EventReminderAudience::Responded => EventResponse::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->id)
                ->whereIn('response', [EventResponseChoice::Going->value, EventResponseChoice::Maybe->value])
                ->exists(),
            EventReminderAudience::Registered => EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->id)
                ->where('status', EventRegistrationStatus::Registered->value)
                ->exists(),
            EventReminderAudience::Rostered => EventRosterMember::query()
                ->where('player_id', $player->id)
                ->whereIn('status', [EventRosterMemberStatus::Assigned->value, EventRosterMemberStatus::Confirmed->value])
                ->whereHas('roster', static fn ($query) => $query->where('occurrence_id', $occurrence->id))
                ->exists(),
            EventReminderAudience::AllScopePlayers => $this->inScope($event, $player),
        };
    }

    private function canReceive(Event $event, Player $player): bool
    {
        try {
            $this->authorization->authorizeSelf($player, $event, $player);

            return true;
        } catch (AuthorizationException) {
            return false;
        }
    }

    private function inScope(Event $event, Player $player): bool
    {
        return match ($event->scope) {
            EventScope::Player => (string) $event->player_id === (string) $player->id,
            EventScope::Kingdom => (string) $event->kingdom_id === (string) $player->current_kingdom_id,
            EventScope::Alliance => $event->alliance_id !== null
                && AllianceRosterEntry::query()
                    ->where('alliance_id', $event->alliance_id)
                    ->where('player_id', $player->id)
                    ->where('state', RosterState::Active->value)
                    ->exists(),
        };
    }

    /** @return list<string> */
    private function targetIds(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;
        if ($event->scope !== EventScope::Player || $event->player_id === null) {
            throw ValidationException::withMessages([
                'audience' => 'Target reminders are available only for Player-scoped Events.',
            ]);
        }

        return [(string) $event->player_id];
    }

    /** @return list<string> */
    private function scopePlayerIds(EventOccurrence $occurrence): array
    {
        $event = $occurrence->event;

        return match ($event->scope) {
            EventScope::Player => $event->player_id === null ? [] : [(string) $event->player_id],
            EventScope::Kingdom => Player::query()
                ->where('current_kingdom_id', $event->kingdom_id)
                ->whereNotNull('user_id')
                ->pluck('id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
            EventScope::Alliance => $this->alliancePlayerIds($event->alliance_id),
        };
    }

    /** @return list<string> */
    private function alliancePlayerIds(?string $allianceId): array
    {
        $alliance = $allianceId === null ? null : Alliance::query()->whereKey($allianceId)->first();
        if (! $alliance instanceof Alliance) {
            return [];
        }

        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('state', RosterState::Active->value)
            ->whereHas('player', static fn ($query) => $query
                ->where('current_kingdom_id', $alliance->kingdom_id)
                ->whereNotNull('user_id'))
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->all();
    }
}
