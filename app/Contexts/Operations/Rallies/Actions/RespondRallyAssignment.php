<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rallies\Actions;

use App\Contexts\Operations\EventCore\Services\EventWriteState;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventCapability;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Services\EventCapabilityGuard;
use App\Contexts\Operations\EventCore\Services\EventAuthorization;
use App\Contexts\Operations\Participation\Services\EventPlayerContextFreezer;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentStatus;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Rallies\Models\RallyGroup;
use App\Contexts\Operations\Rallies\Services\RallyPlayerEligibility;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RespondRallyAssignment
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

    public function handle(Player $actor, RallyAssignment $assignment, Player $player, RallyAssignmentStatus $status): RallyAssignment
    {
        if (! in_array($status, [RallyAssignmentStatus::Confirmed, RallyAssignmentStatus::Declined], true)) {
            throw ValidationException::withMessages(['status' => 'Rally assignment response must be confirmed or declined.']);
        }

        $group = $assignment->rallyGroup()->firstOrFail();
        $event = $group->occurrence()->firstOrFail()->event()->firstOrFail();

        return DB::transaction(function () use ($actor, $assignment, $player, $status, $group, $event): RallyAssignment {
            $context = $this->eventWriteState->lockSelfScope($actor, $event, $player);
            $this->eventAuthority->authorizeSelf($context, $player);
            $this->capabilities->require($context->event, EventCapability::RallyGuidance);

            $occurrence = EventOccurrence::query()
                ->whereKey($group->occurrence_id)
                ->where('event_id', $context->event->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedGroup = RallyGroup::query()
                ->whereKey($group->id)
                ->where('occurrence_id', $occurrence->id)
                ->lockForUpdate()
                ->firstOrFail();
            $alliance = $lockedGroup->alliance()->firstOrFail();

            if ((string) $assignment->player_id !== (string) $context->actor->id
                || ! $this->eligibility->eligible($context->event, $alliance, $context->actor)) {
                throw new AuthorizationException;
            }

            $locked = RallyAssignment::query()
                ->whereKey($assignment->id)
                ->where('rally_group_id', $lockedGroup->id)
                ->where('player_id', $context->actor->id)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($locked->statusEnum(), [RallyAssignmentStatus::Removed, RallyAssignmentStatus::Participated, RallyAssignmentStatus::Absent], true)) {
                throw ValidationException::withMessages(['status' => 'This Rally assignment can no longer be confirmed or declined.']);
            }

            if ($status === RallyAssignmentStatus::Confirmed && ! $locked->statusEnum()->occupiesAssignment()) {
                $occupying = $this->occupyingStatuses();

                if ($locked->roleEnum() === RallyAssignmentRole::Joiner) {
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

                if ($locked->roleEnum() === RallyAssignmentRole::Lead && RallyAssignment::query()
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
                    ->where('player_id', $context->actor->id)
                    ->whereIn('status', $occupying)
                    ->where('id', '!=', $locked->id)
                    ->whereHas('rallyGroup', static fn ($query) => $query
                        ->where('occurrence_id', $occurrence->id)
                        ->where('alliance_id', $alliance->id))
                    ->exists()) {
                    throw ValidationException::withMessages(['status' => 'This Player has another active Rally assignment for the same Alliance.']);
                }
            }

            if ($status === RallyAssignmentStatus::Confirmed) {
                $this->contexts->freeze($occurrence, $context->actor, $alliance);
            }

            $locked->forceFill([
                'status' => $status,
                'responded_by_player_id' => $context->actor->id,
                'responded_at' => now(),
            ])->save();

            $metadata = [
                'event_id' => (string) $context->event->id,
                'occurrence_id' => (string) $occurrence->id,
                'alliance_id' => (string) $alliance->id,
                'rally_group_id' => (string) $lockedGroup->id,
                'player_id' => (string) $context->actor->id,
                'status' => $status->value,
            ];
            $this->audit->record('rally.assignment.responded', $context->actor, $locked, $alliance, $metadata);
            $this->outbox->record(
                'rally.assignment.responded',
                (string) $alliance->id,
                $locked,
                $metadata,
                partitionKey: $context->event->scopeEnum()->value.':'.$context->target->id,
            );

            return $locked->refresh()->load(['rallyGroup', 'player']);
        });
    }

    /** @return list<string> */
    private function occupyingStatuses(): array
    {
        return array_map(
            static fn (RallyAssignmentStatus $status): string => $status->value,
            array_values(array_filter(
                RallyAssignmentStatus::cases(),
                static fn (RallyAssignmentStatus $status): bool => $status->occupiesAssignment(),
            )),
        );
    }
}
