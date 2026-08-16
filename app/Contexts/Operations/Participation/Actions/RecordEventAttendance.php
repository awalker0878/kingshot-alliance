<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Actions;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventPlayerContext;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventMutationAuthority;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Services\EventParticipantAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RecordEventAttendance
{
    public function __construct(
        private EventMutationAuthority $mutations,
        private EventParticipantAuthorization $participants,
        private EventCapabilityGuard $capabilities,
        private EventPlayerContextFreezer $contexts,
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
        $event = $occurrence->event()->firstOrFail();

        return DB::transaction(function () use ($actor, $occurrence, $event, $player, $status, $notes): EventAttendance {
            $context = $this->mutations->requireManager($actor, $event);
            $this->capabilities->require($context->event, EventCapability::Attendance);

            $lockedOccurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $currentPlayer = (string) $context->actor->id === (string) $player->id
                ? $context->actor
                : Player::query()->whereKey($player->id)->firstOrFail();
            $frozenContext = $this->contexts->existing($lockedOccurrence, $currentPlayer);

            if (! $frozenContext instanceof EventPlayerContext) {
                if ((string) $context->actor->id !== (string) $currentPlayer->id) {
                    $currentPlayer = Player::query()
                        ->whereKey($currentPlayer->id)
                        ->lockForUpdate()
                        ->firstOrFail();
                }

                if (! $this->participants->eligible($context->event, $currentPlayer)) {
                    throw new AuthorizationException;
                }

                if ($status !== EventAttendanceStatus::Unknown) {
                    $this->contexts->freeze($lockedOccurrence, $currentPlayer);
                }
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
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

            return $record->refresh();
        });
    }
}
