<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\EventCore\Models\EventResponse;

final class EventParticipationQuery
{
    /** @return array{response:?array<string,mixed>,registration:?array<string,mixed>,attendance:?array<string,mixed>} */
    public function forPlayer(EventOccurrence $occurrence, Player $player): array
    {
        $response = EventResponse::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->first();
        $registration = EventRegistration::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->first();
        $attendance = EventAttendance::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->first();

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

        $responses = EventResponse::query()->whereIn('occurrence_id', $occurrenceIds)->with('player')->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $registrations = EventRegistration::query()->whereIn('occurrence_id', $occurrenceIds)->with('player')->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $attendance = EventAttendance::query()->whereIn('occurrence_id', $occurrenceIds)->with('player')->get()->keyBy(fn ($row): string => $row->occurrence_id.':'.$row->player_id);
        $keys = $responses->keys()->merge($registrations->keys())->merge($attendance->keys())->unique()->sort()->values();

        return $keys->map(function (string $key) use ($responses, $registrations, $attendance): array {
            [$occurrenceId, $playerId] = explode(':', $key, 2);
            $response = $responses->get($key);
            $registration = $registrations->get($key);
            $attendanceRecord = $attendance->get($key);
            $player = $response?->player ?? $registration?->player ?? $attendanceRecord?->player;

            return [
                'occurrenceId' => $occurrenceId,
                'playerId' => $playerId,
                'playerName' => $player instanceof Player ? (string) $player->current_name : 'Unknown Player',
                'response' => $response?->response?->value,
                'registration' => $registration?->status?->value,
                'waitlistPosition' => $registration?->waitlist_position,
                'attendance' => $attendanceRecord?->status?->value,
                'attendanceNotes' => $attendanceRecord?->notes,
            ];
        })->all();
    }
}
