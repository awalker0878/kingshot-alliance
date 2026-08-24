<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;

final readonly class EventParticipationQuery
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private EventEligiblePlayerQuery $eligiblePlayers,
    ) {}

    /** @return array{response:?array<string,mixed>,registration:?array<string,mixed>,attendance:?array<string,mixed>} */
    public function forPlayer(EventOccurrence $occurrence, PlayerReference $player): array
    {
        return $this->forPlayerOccurrences([(string) $occurrence->id], $player)[(string) $occurrence->id]
            ?? ['response' => null, 'registration' => null, 'attendance' => null];
    }

    /**
     * @param  list<string>  $occurrenceIds
     * @return array<string,array{response:?array<string,mixed>,registration:?array<string,mixed>,attendance:?array<string,mixed>}>
     */
    public function forPlayerOccurrences(array $occurrenceIds, PlayerReference $player): array
    {
        $occurrenceIds = array_slice(array_values(array_unique(array_filter(
            array_map('strval', $occurrenceIds),
            static fn (string $id): bool => $id !== '',
        ))), 0, 500);
        if ($occurrenceIds === []) {
            return [];
        }

        $responses = EventResponse::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->get()
            ->keyBy('occurrence_id');
        $registrations = EventRegistration::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->get()
            ->keyBy('occurrence_id');
        $attendance = EventAttendance::query()
            ->where('player_id', $player->playerId)
            ->whereIn('occurrence_id', $occurrenceIds)
            ->get()
            ->keyBy('occurrence_id');

        $result = [];
        foreach ($occurrenceIds as $occurrenceId) {
            $response = $responses->get($occurrenceId);
            $registration = $registrations->get($occurrenceId);
            $attendanceRecord = $attendance->get($occurrenceId);
            $result[$occurrenceId] = [
                'response' => $response instanceof EventResponse ? [
                    'id' => (string) $response->id,
                    'response' => $response->response->value,
                    'preferredRole' => $response->preferred_role,
                    'preferredTeam' => $response->preferred_team,
                    'availableFrom' => $response->available_from?->toIso8601String(),
                    'availableUntil' => $response->available_until?->toIso8601String(),
                    'note' => $response->note,
                    'updatedAt' => $response->updated_at?->toIso8601String(),
                ] : null,
                'registration' => $registration instanceof EventRegistration ? [
                    'id' => (string) $registration->id,
                    'status' => $registration->status->value,
                    'waitlistPosition' => $registration->waitlist_position,
                    'registeredAt' => $registration->registered_at?->toIso8601String(),
                    'updatedAt' => $registration->updated_at?->toIso8601String(),
                ] : null,
                'attendance' => $attendanceRecord instanceof EventAttendance ? [
                    'id' => (string) $attendanceRecord->id,
                    'status' => $attendanceRecord->status->value,
                    'notes' => $attendanceRecord->notes,
                    'recordedAt' => $attendanceRecord->recorded_at?->toIso8601String(),
                    'updatedAt' => $attendanceRecord->updated_at?->toIso8601String(),
                ] : null,
            ];
        }

        return $result;
    }

    /**
     * Bounded owner summary consumed by EventManagement Event Command composition.
     *
     * @return array{
     *   eligibleCount:int,
     *   responseCount:int,
     *   unansweredCount:int,
     *   registeredCount:int,
     *   waitlistCount:int,
     *   cancelledRegistrationCount:int,
     *   attendanceRecordedCount:int,
     *   attendanceUnknownCount:int,
     *   attendanceMissingCount:int
     * }
     */
    public function commandSummary(EventOccurrence $occurrence): array
    {
        $occurrence->loadMissing('event');
        $eligibleIds = $this->eligiblePlayers->for($occurrence->event)
            ->map(static fn (PlayerReference $player): string => $player->playerId)
            ->values()
            ->all();
        $eligibleCount = count($eligibleIds);

        if ($eligibleIds === []) {
            return [
                'eligibleCount' => 0,
                'responseCount' => 0,
                'unansweredCount' => 0,
                'registeredCount' => 0,
                'waitlistCount' => 0,
                'cancelledRegistrationCount' => 0,
                'attendanceRecordedCount' => 0,
                'attendanceUnknownCount' => 0,
                'attendanceMissingCount' => 0,
            ];
        }

        $responseCount = EventResponse::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereIn('player_id', $eligibleIds)
            ->count();
        $registrations = EventRegistration::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereIn('player_id', $eligibleIds)
            ->get(['status']);
        $attendance = EventAttendance::query()
            ->where('occurrence_id', $occurrence->id)
            ->whereIn('player_id', $eligibleIds)
            ->get(['status']);
        $attendanceUnknownCount = $attendance
            ->filter(static fn (EventAttendance $row): bool => $row->status === EventAttendanceStatus::Unknown)
            ->count();

        return [
            'eligibleCount' => $eligibleCount,
            'responseCount' => $responseCount,
            'unansweredCount' => max(0, $eligibleCount - $responseCount),
            'registeredCount' => $registrations
                ->filter(static fn (EventRegistration $row): bool => $row->status === EventRegistrationStatus::Registered)
                ->count(),
            'waitlistCount' => $registrations
                ->filter(static fn (EventRegistration $row): bool => $row->status === EventRegistrationStatus::Waitlisted)
                ->count(),
            'cancelledRegistrationCount' => $registrations
                ->filter(static fn (EventRegistration $row): bool => $row->status === EventRegistrationStatus::Cancelled)
                ->count(),
            'attendanceRecordedCount' => max(0, $attendance->count() - $attendanceUnknownCount),
            'attendanceUnknownCount' => $attendanceUnknownCount,
            'attendanceMissingCount' => max(0, $eligibleCount - $attendance->count() + $attendanceUnknownCount),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function management(Event $event): array
    {
        $occurrenceIds = $event->occurrences->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        if ($occurrenceIds === []) {
            return [];
        }

        $responses = EventResponse::query()->whereIn('occurrence_id', $occurrenceIds)->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $registrations = EventRegistration::query()->whereIn('occurrence_id', $occurrenceIds)->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $attendance = EventAttendance::query()->whereIn('occurrence_id', $occurrenceIds)->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $keys = $responses->keys()->merge($registrations->keys())->merge($attendance->keys())->unique()->sort()->values();
        $playerIds = $keys->map(static fn (string $key): string => explode(':', $key, 2)[1])->unique()->values()->all();
        $players = $this->players->byIds($playerIds);

        return array_values($keys->map(function (string $key) use ($responses, $registrations, $attendance, $players): array {
            [$occurrenceId, $playerId] = explode(':', $key, 2);
            $response = $responses->get($key);
            $registration = $registrations->get($key);
            $attendanceRecord = $attendance->get($key);
            $player = $players[$playerId] ?? null;

            return [
                'occurrenceId' => $occurrenceId,
                'playerId' => $playerId,
                'playerName' => $player instanceof PlayerReference ? $player->currentName : 'Unknown Player',
                'response' => $response instanceof EventResponse ? $response->response->value : null,
                'registration' => $registration instanceof EventRegistration ? $registration->status->value : null,
                'waitlistPosition' => $registration instanceof EventRegistration ? $registration->waitlist_position : null,
                'attendance' => $attendanceRecord instanceof EventAttendance ? $attendanceRecord->status->value : null,
                'attendanceNotes' => $attendanceRecord instanceof EventAttendance ? $attendanceRecord->notes : null,
            ];
        })->all());
    }
}
