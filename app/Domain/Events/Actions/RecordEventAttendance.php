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
use App\Domain\Events\Services\EventMutationAuthority;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordEventAttendance
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
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
        $occurrence->loadMissing('event');
        $event = $occurrence->event;

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $status, $notes): EventAttendance {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Attendance);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->sharedLock()
                ->firstOrFail();

            // Player identity is the eligibility anchor. Kingdom transfer and roster
            // lifecycle workflows acquire this Player before changing scope-bound state.
            $currentPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (! $this->participants->eligible($context->event, $currentPlayer)) {
                throw new AuthorizationException;
            }

            $record = EventAttendance::query()->updateOrCreate(
                [
                    'occurrence_id' => $lockedOccurrence->id,
                    'player_id' => $currentPlayer->id,
                ],
                [
                    'status' => $status,
                    'notes' => $notes === null || trim($notes) === '' ? null : trim($notes),
                    'recorded_by_player_id' => $context->actor->id,
                    'recorded_at' => now(),
                ],
            );

            $alliance = $context->target instanceof Alliance ? $context->target : null;
            $metadata = [
                'occurrence_id' => (string) $lockedOccurrence->id,
                'player_id' => (string) $currentPlayer->id,
                'status' => $status->value,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record('event.attendance.recorded', $context->actor, $record, $alliance, $metadata);
            $this->outbox->record(
                'event.attendance.recorded',
                $alliance?->id,
                $record,
                $metadata,
                partitionKey: $context->event->scope->value.':'.$context->target->id,
            );

            return $record->refresh();
        });
    }
}
