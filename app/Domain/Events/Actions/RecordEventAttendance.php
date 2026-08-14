<?php

declare(strict_types=1);

namespace App\Domain\Events\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventAttendance;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordEventAttendance
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        EventOccurrence $occurrence,
        Player $player,
        EventAttendanceStatus $status,
        ?string $notes = null,
    ): EventAttendance {
        $occurrence->loadMissing('event.typeScope');
        $event = $occurrence->event;
        $this->capabilities->require($event, EventCapability::Attendance);
        $this->authorization->authorizeManager($actor, $event);
        if (! $this->authorization->eligible($event, $player)) {
            throw new AuthorizationException;
        }

        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $status, $notes, $target): EventAttendance {
            $record = EventAttendance::query()->updateOrCreate(
                ['occurrence_id' => $occurrence->id, 'player_id' => $player->id],
                [
                    'status' => $status,
                    'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                    'recorded_by_player_id' => $actor->id,
                    'recorded_at' => now(),
                ],
            );

            $alliance = $target instanceof Alliance ? $target : null;
            $metadata = [
                'occurrence_id' => (string) $occurrence->id,
                'player_id' => (string) $player->id,
                'status' => $status->value,
                'actor_player_id' => $actor->id,
            ];
            $this->audit->record('event.attendance.recorded', $actor, $record, $alliance, $metadata);
            $this->outbox->record('event.attendance.recorded', $alliance?->id, $record, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $record->refresh();
        });
    }
}
