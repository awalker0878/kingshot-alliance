<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;

final readonly class EventParticipationQuery
{
    public function __construct(private PlayerReferenceQuery $players) {}

    /** @return array{response:?array<string,mixed>,registration:?array<string,mixed>,attendance:?array<string,mixed>} */
    public function forPlayer(EventOccurrence $occurrence, PlayerReference $player): array
    {
        return $this->forPlayerOccurrences([(string) $occurrence->id], $player)[(string) $occurrence->id]
            ?? ['response' => null, 'registration' => null, 'attendance' => null];
    }

    /**
     * @param list<string> $occurrenceIds
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
