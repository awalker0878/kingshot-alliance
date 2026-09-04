<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AssignMembershipRole
{
    public function __construct(
        private AllianceWriteState $allianceWriteState,
        private AllianceAuthorization $authority,
        private AuditRecorder $audit,
    ) {}

    public function handle(string $allianceId, string $actorPlayerId, string $membershipId, string $roleId): string
    {
        return DB::transaction(function () use ($allianceId, $actorPlayerId, $membershipId, $roleId): string {
            $context = $this->allianceWriteState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authority->authorizeContext($context, AlliancePermission::RoleManage);

            $membership = AllianceMembership::query()
                ->whereKey($membershipId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->status !== MembershipStatus::Active) {
                throw ValidationException::withMessages(['membership' => 'Only active memberships can receive specialist roles.']);
            }

            $role = Role::query()
                ->whereKey($roleId)
                ->where('alliance_id', $context->alliance->id)
                ->with('permissions:id,key')
                ->sharedLock()
                ->firstOrFail();

            if ($role->archived_at !== null) {
                throw ValidationException::withMessages(['role' => 'Archived specialist roles cannot be assigned.']);
            }

            if ((string) $membership->player_id === (string) $context->actor->playerId) {
                foreach ($role->permissions as $permissionModel) {
                    $permission = AlliancePermission::tryFrom((string) $permissionModel->key);
                    if ($permission === null || ! $this->authority->allowsContext($context, $permission)) {
                        throw ValidationException::withMessages(['role' => 'A specialist role cannot be used to increase your own authority.']);
                    }
                }
            }

            if (! $membership->roles()->where('roles.id', $role->id)->exists()) {
                $membership->roles()->attach($role->id, ['alliance_id' => $context->alliance->id]);

                $metadata = ['role_id' => $role->id, 'role_key' => $role->key, 'player_id' => $membership->player_id];
                $this->audit->record('membership.role_assigned', $context->actor, $membership, $context->alliance, $metadata);
                OutboxMessage::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'partition_key' => 'alliance:'.$context->alliance->id,
                    'event_type' => 'membership.role_assigned',
                    'aggregate_type' => AllianceMembership::class,
                    'aggregate_id' => $membership->id,
                    'idempotency_key' => 'membership.role_assigned:'.$membership->id.':'.$role->id.':'.Str::ulid(),
                    'payload' => ['alliance_id' => $context->alliance->id, 'membership_id' => $membership->id, 'player_id' => $membership->player_id, 'role_id' => $role->id, 'role_key' => $role->key],
                    'occurred_at' => now(), 'available_at' => now(), 'attempts' => 0,
                ]);
            }

            return (string) $membership->id;
        });
    }
}
