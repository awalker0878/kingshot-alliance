<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\TransferPlanState;
use App\Domain\Kingdoms\Models\TransferGroup;
use App\Domain\Kingdoms\Models\TransferPlan;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveTransferGroup
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array{name: string, coordinator_membership_ids: array<int, string>} $attributes */
    public function handle(
        Alliance $alliance,
        User $actor,
        string $planId,
        array $attributes,
        ?string $groupId = null,
    ): TransferGroup {
        if ($this->authorization->allows($actor, $alliance, PermissionKey::KingdomManage) === false) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $planId, $attributes, $groupId): TransferGroup {
            $currentAlliance = Alliance::query()
                ->lockForUpdate()
                ->findOrFail($alliance->id);

            $plan = TransferPlan::query()
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->findOrFail($planId);

            $this->assertMutable($currentAlliance, $plan);

            $group = $groupId === null
                ? new TransferGroup([
                    'alliance_id' => $currentAlliance->id,
                    'transfer_plan_id' => $plan->id,
                ])
                : TransferGroup::query()
                    ->where('alliance_id', $currentAlliance->id)
                    ->where('transfer_plan_id', $plan->id)
                    ->lockForUpdate()
                    ->findOrFail($groupId);

            if ($group->exists && $group->archived_at !== null) {
                throw ValidationException::withMessages([
                    'group' => 'Archived transfer groups cannot be edited.',
                ]);
            }

            $name = trim($attributes['name']);
            if ($name === '') {
                throw ValidationException::withMessages([
                    'name' => 'A transfer group name is required.',
                ]);
            }

            $duplicate = TransferGroup::query()
                ->where('transfer_plan_id', $plan->id)
                ->whereNull('archived_at')
                ->whereRaw('lower(name) = lower(?)', [$name]);

            if ($group->exists) {
                $duplicate->where('id', '<>', $group->id);
            }

            if ($duplicate->exists()) {
                throw ValidationException::withMessages([
                    'name' => 'An active transfer group with that name already exists in this cycle.',
                ]);
            }

            $coordinatorIds = collect($attributes['coordinator_membership_ids'])
                ->map(static fn (string $id): string => trim($id))
                ->filter(static fn (string $id): bool => $id !== '')
                ->unique()
                ->sort()
                ->values()
                ->all();

            $memberships = AllianceMembership::query()
                ->where('alliance_id', $currentAlliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->whereIn('id', $coordinatorIds)
                ->lockForUpdate()
                ->get();

            if ($memberships->count() !== count($coordinatorIds)) {
                throw ValidationException::withMessages([
                    'coordinator_membership_ids' => 'Every coordinator must be an active membership in this alliance.',
                ]);
            }

            $existingCoordinatorIds = $group->exists
                ? DB::table('transfer_group_coordinators')
                    ->where('transfer_group_id', $group->id)
                    ->pluck('membership_id')
                    ->map(static fn (mixed $id): string => (string) $id)
                    ->sort()
                    ->values()
                    ->all()
                : [];

            $isNew = ! $group->exists;
            $nameChanged = $isNew || $group->name !== $name;
            $coordinatorsChanged = $existingCoordinatorIds !== $coordinatorIds;

            if (! $isNew && ! $nameChanged && ! $coordinatorsChanged) {
                return $group->load('coordinators.user:id,name,email');
            }

            $group->forceFill([
                'name' => $name,
                'archived_at' => null,
            ])->save();

            if ($coordinatorsChanged || $isNew) {
                $group->coordinators()->syncWithPivotValues($coordinatorIds, [
                    'alliance_id' => (string) $currentAlliance->id,
                    'transfer_plan_id' => (string) $plan->id,
                ]);
            }

            $event = $isNew
                ? 'kingdoms.transfer_group_created'
                : 'kingdoms.transfer_group_updated';
            $metadata = [
                'transfer_plan_id' => (string) $plan->id,
                'transfer_group_id' => (string) $group->id,
                'coordinator_membership_ids' => $coordinatorIds,
            ];

            $this->audit->record($event, $actor, $group, $currentAlliance, $metadata);
            $this->outbox->record($event, (string) $currentAlliance->id, $group, $metadata);

            return $group->refresh()->load('coordinators.user:id,name,email');
        });
    }

    private function assertMutable(Alliance $alliance, TransferPlan $plan): void
    {
        if (! in_array($plan->state, [TransferPlanState::Draft, TransferPlanState::Open], true)) {
            throw ValidationException::withMessages([
                'group' => 'Transfer groups can only be changed while the transfer cycle is Draft or Open.',
            ]);
        }

        if ($alliance->kingdom_id !== $plan->home_kingdom_id) {
            throw ValidationException::withMessages([
                'group' => 'The alliance Kingdom changed after this transfer cycle was created. Cancel the stale cycle before changing groups.',
            ]);
        }
    }
}
