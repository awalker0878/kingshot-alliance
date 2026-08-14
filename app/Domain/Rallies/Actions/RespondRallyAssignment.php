<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Services\EventCapabilityGuard;
use App\Domain\Events\Services\EventParticipantAuthorization;
use App\Domain\Events\Services\EventTargetResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Rallies\Services\RallyPlayerEligibility;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondRallyAssignment
{
    public function __construct(
        private EventParticipantAuthorization $authorization,
        private EventCapabilityGuard $capabilities,
        private RallyPlayerEligibility $eligibility,
        private EventTargetResolver $targets,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, RallyAssignment $assignment, Player $player, RallyAssignmentStatus $status): RallyAssignment
    {
        if (! in_array($status, [RallyAssignmentStatus::Confirmed, RallyAssignmentStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Rally assignment response must be confirmed or declined.']);
        }
        $assignment->loadMissing('rallyGroup.occurrence.event.typeScope', 'rallyGroup.alliance');
        $group = $assignment->rallyGroup;
        $occurrence = $group->occurrence;
        $event = $occurrence->event;
        $alliance = $group->alliance;
        $this->capabilities->require($event, EventCapability::RallyGuidance);
        $this->authorization->authorizeSelf($actor, $event, $player);
        if ((string) $assignment->player_id !== (string) $player->id || ! $this->eligibility->eligible($event, $alliance, $player)) {
            throw new AuthorizationException;
        }
        $target = $this->targets->forEvent($event);

        return DB::transaction(function () use ($actor, $assignment, $player, $status, $group, $occurrence, $event, $alliance, $target): RallyAssignment {
            EventOccurrence::query()->whereKey($occurrence->id)->lockForUpdate()->firstOrFail();
            $lockedGroup = RallyGroup::query()->whereKey($group->id)->lockForUpdate()->firstOrFail();
            $locked = RallyAssignment::query()->whereKey($assignment->id)->where('player_id', $player->id)->lockForUpdate()->firstOrFail();
            if (in_array($locked->status, [RallyAssignmentStatus::Removed, RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This Rally assignment can no longer be confirmed or declined.']);
            }

            if ($status === RallyAssignmentStatus::Confirmed && ! $locked->status->occupiesAssignment()) {
                $occupying = $this->occupyingStatuses();
                if ($locked->role === RallyAssignmentRole::Joiner) {
                    $joiners = RallyAssignment::query()
                        ->where('rally_group_id', $lockedGroup->id)
                        ->where('role', RallyAssignmentRole::Joiner->value)
                        ->whereIn('status', $occupying)
                        ->where('id', '!=', $locked->id)
                        ->count();
                    if ($lockedGroup->max_joiners !== null && $joiners >= (int) $lockedGroup->max_joiners) {
                        throw ValidationException::withMessages(['status' => 'This Rally group has reached its maximum joiners.']);
                    }
                }
                if ($locked->role === RallyAssignmentRole::Lead && RallyAssignment::query()
                    ->where('rally_group_id', $lockedGroup->id)
                    ->where('role', RallyAssignmentRole::Lead->value)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'The Rally lead slot has been reassigned.']);
                }
                if ($locked->slot_number !== null && RallyAssignment::query()
                    ->where('rally_group_id', $lockedGroup->id)
                    ->where('slot_number', $locked->slot_number)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Rally slot has been reassigned.']);
                }
                if (RallyAssignment::query()
                    ->where('player_id', $player->id)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->whereHas('rallyGroup', static fn ($query) => $query
                        ->where('occurrence_id', $occurrence->id)
                        ->where('alliance_id', $alliance->id))
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active Rally assignment for the same Alliance.']);
                }
            }

            $locked->forceFill([
                'status' => $status,
                'responded_by_player_id' => $player->id,
                'responded_at' => now(),
            ])->save();
            $metadata = [
                'event_id' => (string) $event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => (string) $alliance->id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $player->id,
                'status' => $status->value,
            ];
            $this->audit->record('rally.assignment.responded', $actor, $locked, $alliance, $metadata);
            $this->outbox->record('rally.assignment.responded', (string) $alliance->id, $locked, $metadata, partitionKey: $event->scope->value.':'.$target->id);

            return $locked->refresh()->load(['rallyGroup', 'player']);
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_map(
            static fn (RallyAssignmentStatus $status): string => $status->value,
            array_values(array_filter(RallyAssignmentStatus::cases(), static fn (RallyAssignmentStatus $status): bool => $status->occupiesAssignment())),
        );
    }
}
