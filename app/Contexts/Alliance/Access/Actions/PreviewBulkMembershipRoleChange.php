<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceRoleDelegation;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Validation\ValidationException;

final readonly class PreviewBulkMembershipRoleChange
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AllianceRoleDelegation $delegation,
    ) {}

    /**
     * @param  list<string>  $membershipIds
     * @return array<string, mixed>
     */
    public function handle(string $allianceId, string $actorPlayerId, array $membershipIds, string $roleId, string $operation): array
    {
        $membershipIds = array_values(array_unique($membershipIds));
        if ($membershipIds === [] || count($membershipIds) > 50) {
            throw ValidationException::withMessages(['membership_ids' => 'Select between 1 and 50 Alliance memberships.']);
        }
        if (! in_array($operation, ['assign', 'remove'], true)) {
            throw ValidationException::withMessages(['operation' => 'Choose assign or remove.']);
        }
        $this->authorization->authorize($actorPlayerId, $allianceId, AlliancePermission::RoleManage);

        $role = Role::query()->whereKey($roleId)->where('alliance_id', $allianceId)->with('permissions:id,key')->firstOrFail();
        if ($operation === 'assign' && $role->archived_at !== null) {
            throw ValidationException::withMessages(['role' => 'Archived specialist roles cannot be assigned.']);
        }
        $alliance = Alliance::query()->findOrFail($allianceId);
        $actor = AllianceMembership::query()->where('alliance_id', $allianceId)
            ->where('player_id', $actorPlayerId)->where('status', MembershipStatus::Active->value)->firstOrFail();
        // One role/actor pair has only two recipient policies. Do not repeat
        // role/permission reads for every selected membership.
        $canAssignSelf = $operation === 'assign' && $this->delegation->allows($actor, $alliance, $role, true);
        $canAssignOthers = $operation === 'assign' && $this->delegation->allows($actor, $alliance, $role, false);

        $rows = AllianceMembership::query()->where('alliance_id', $allianceId)->whereIn('id', $membershipIds)->with('roles:id')->get()->keyBy('id');
        $items = [];
        foreach ($membershipIds as $membershipId) {
            $membership = $rows->get($membershipId);
            $outcome = 'ready';
            $code = 'ready';
            if (! $membership instanceof AllianceMembership) {
                $outcome = 'blocked';
                $code = 'membership_not_found';
            } elseif ($membership->status !== MembershipStatus::Active) {
                $outcome = 'blocked';
                $code = 'membership_inactive';
            } elseif ($operation === 'assign' && ! ((string) $membership->player_id === $actorPlayerId ? $canAssignSelf : $canAssignOthers)) {
                $outcome = 'blocked';
                $code = 'permission_delegation_denied';
            } else {
                $assigned = $membership->roles->contains(static fn (Role $assignedRole): bool => (string) $assignedRole->id === $roleId);
                if ($operation === 'assign' && $assigned) {
                    $outcome = 'skipped';
                    $code = 'already_assigned';
                } elseif ($operation === 'remove' && ! $assigned) {
                    $outcome = 'skipped';
                    $code = 'not_assigned';
                }
            }
            $items[] = [
                'itemId' => $membershipId,
                'playerId' => $membership instanceof AllianceMembership ? (string) $membership->player_id : null,
                'roleId' => $roleId,
                'roleKey' => (string) $role->key,
                'operation' => $operation,
                'outcome' => $outcome,
                'code' => $code,
            ];
        }

        return [
            'operation' => $operation,
            'roleId' => $roleId,
            'roleKey' => (string) $role->key,
            'items' => $items,
            'ready' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'ready')),
            'blocked' => count(array_filter($items, static fn (array $item): bool => $item['outcome'] === 'blocked')),
        ];
    }
}
