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
        $response = EventResponse::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->playerId)->first();
        $registration = EventRegistration::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->playerId)->first();
        $attendance = EventAttendance::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->playerId)->first();

        return [
            'response' => $response === null ? null : [
                'response' => $response->response->value,
                'preferredRole' => $response->preferred_role,
                'preferredTeam' => $response->preferred_team,
                'availableFrom' => $response->available_from?->toIso8601String(),
                'availableUntil' => $response->available_until?->toIso8601String(),
                'note' => $response->note,
            ],
            'registration' => $registration === null ? null : [
                'status' => $registration->status->value,
                'waitlistPosition' => $registration->waitlist_position,
                'registeredAt' => $registration->registered_at?->toIso8601String(),
            ],
            'attendance' => $attendance === null ? null : [
                'status' => $attendance->status->value,
                'notes' => $attendance->notes,
                'recordedAt' => $attendance->recorded_at?->toIso8601String(),
            ],
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
