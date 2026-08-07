<?php

declare(strict_types=1);

namespace App\Domain\Rallies\Actions;

use App\Domain\Events\Services\EventOutbox;

use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Rallies\Enums\RallyAssignmentRole;
use App\Domain\Rallies\Enums\RallyAssignmentStatus;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Alliances\Models\AllianceMembership;
use App\Domain\Rallies\Models\RallyAssignment;
use App\Domain\Rallies\Models\RallyGroup;
use App\Domain\Identity\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class AssignRallyMember
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private EventOutbox $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        RallyGroup $group,
        AllianceMembership $membership,
        RallyAssignmentRole $role,
        ?int $slotNumber = null,
    ): RallyAssignment {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::EventManage)) {
            throw new AuthorizationException('You are not allowed to assign rally members.');
        }

        if ($group->alliance_id !== $alliance->id
            || $membership->alliance_id !== $alliance->id
            || $membership->status !== MembershipStatus::Active) {
            throw new AuthorizationException('The rally assignment must use an active membership from the active alliance.');
        }

        if ($role === RallyAssignmentRole::Lead && ($slotNumber === null || $slotNumber < 1)) {
            throw new InvalidArgumentException('Rally leads require a positive slot number.');
        }

        if ($slotNumber !== null && $slotNumber < 1) {
            throw new InvalidArgumentException('Rally slot number must be positive.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $group,
            $membership,
            $role,
            $slotNumber,
        ): RallyAssignment {
            $lockedGroup = RallyGroup::query()
                ->whereKey($group->id)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $existing = RallyAssignment::query()
                ->where('rally_group_id', $lockedGroup->id)
                ->where('membership_id', $membership->id)
                ->lockForUpdate()
                ->first();

            $status = RallyAssignmentStatus::Assigned;

            if ($role === RallyAssignmentRole::Joiner && $lockedGroup->max_joiners !== null) {
                $assignedJoinersQuery = RallyAssignment::query()
                    ->where('rally_group_id', $lockedGroup->id)
                    ->where('role', RallyAssignmentRole::Joiner->value)
                    ->where('status', RallyAssignmentStatus::Assigned->value);

                if ($existing instanceof RallyAssignment) {
                    $assignedJoinersQuery->where('id', '!=', $existing->id);
                }

                if ($assignedJoinersQuery->count() >= $lockedGroup->max_joiners) {
                    $status = RallyAssignmentStatus::Standby;
                }
            }

            if ($existing instanceof RallyAssignment) {
                $existing->forceFill([
                    'role' => $role,
                    'slot_number' => $slotNumber,
                    'status' => $status,
                    'participation_recorded_at' => null,
                    'assigned_by_user_id' => $actor->id,
                ])->save();
                $assignment = $existing;
            } else {
                $assignment = RallyAssignment::query()->create([
                    'alliance_id' => $alliance->id,
                    'rally_group_id' => $lockedGroup->id,
                    'membership_id' => $membership->id,
                    'role' => $role,
                    'slot_number' => $slotNumber,
                    'status' => $status,
                    'assigned_by_user_id' => $actor->id,
                ]);
            }

            $this->audit->record('rally.assignment.saved', $actor, $assignment, $alliance, [
                'group_id' => $lockedGroup->id,
                'membership_id' => $membership->id,
                'role' => $role->value,
                'status' => $status->value,
            ]);
            $this->outbox->record('rally.assignment.saved', $alliance, $assignment, [
                'group_id' => $lockedGroup->id,
                'membership_id' => $membership->id,
                'role' => $role->value,
                'status' => $status->value,
            ]);

            return $assignment;
        });
    }
}
