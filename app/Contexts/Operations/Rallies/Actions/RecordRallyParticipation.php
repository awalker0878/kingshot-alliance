<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventCapabilityGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Participation\Models\EventPlayerContext;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyPlayerEligibility;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordRallyParticipation
{
    public function __construct(
        private EventWriteState $eventWriteState,
        private EventAuthorization $eventAuthority,
        private EventCapabilityGuard $capabilities,
        private RallyPlayerEligibility $eligibility,
        private EventPlayerContextFreezer $contexts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, RallyAssignment $assignment, RallyAssignmentStatus $status): RallyAssignment
    {
        if (! in_array($status, [RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
            throw ValidationException::withMessages(['status' => 'Participation must be recorded as participated or absent.']);
        }

        $group = $assignment->rallyGroup()->firstOrFail();
        $event = $group->occurrence()->firstOrFail()->event()->firstOrFail();

        return DB::transaction(function () use ($actor, $assignment, $status, $group, $event): RallyAssignment {
            $context = $this->eventWriteState->lockEventScope($actor, $event);
            $this->eventAuthority->authorizeManager($context);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            $occurrence = EventOccurrence::query()
                ->whereKey($group->occurrence_id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGroup = RallyGroup::query()
                ->whereKey($group->id)
                ->where('occurrence_id', $occurrence->id)
                ->sharedLock()
                ->firstOrFail();
            $alliance = $lockedGroup->alliance()->firstOrFail();

            $locked = RallyAssignment::query()
                ->whereKey($assignment->id)
                ->where('rally_group_id', $lockedGroup->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->statusEnum(), [RallyAssignmentStatus::Declined, RallyAssignmentStatus::Removed], true)) {
                throw ValidationException::withMessages(['status' => 'Declined or removed assignments cannot receive participation.']);
            }

            $player = (string) $context->actor->id === (string) $locked->player_id
                ? $context->actor
                : Player::query()->whereKey($locked->player_id)->firstOrFail();
            $frozenContext = $this->contexts->existing($occurrence, $player);

            if (! $frozenContext instanceof EventPlayerContext) {
                if ((string) $context->actor->id !== (string) $player->id) {
                    $player = Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
                }
                if (! $this->eligibility->eligible($context->event, $alliance, $player)) {
                    throw ValidationException::withMessages([
                        'player' => 'This Player is not eligible for this Rally Alliance.',
                    ]);
                }

                $this->contexts->freeze($occurrence, $player, $alliance);
            }

            $locked->forceFill([
                'status' => $status,
                'recorded_by_player_id' => $context->actor->id,
                'recorded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $lockedGroup->occurrence_id,
                'alliance_id' => (string) $lockedGroup->alliance_id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $locked->player_id,
                'status' => $status->value,
                'actor_player_id' => (string) $context->actor->id,
            ];
            $this->audit->record('rally.participation.recorded', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'rally.participation.recorded',
                (string) $lockedGroup->alliance_id,
                $locked,
                $metadata,
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

            return $locked->refresh();
        });
    }
}
