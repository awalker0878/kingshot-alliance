<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Models\Alliance;
use App\Models\AllianceMembership;
use App\Models\OutboxMessage;
use App\Models\Role;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RemoveMembershipRole
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private MembershipAdministrationGuard $guard,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $membershipId,
        string $roleId,
    ): AllianceMembership {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RoleManage)) {
            throw new AuthorizationException();
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $roleId): AllianceMembership {
            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $role = Role::query()
                ->where('id', $roleId)
                ->where('alliance_id', $alliance->id)
                ->firstOrFail();

            if ((string) $role->key === DefaultAllianceRole::Owner->value) {
                $this->guard->assertCanChangeOwnerMembership($membership);
            }

            $membership->roles()->detach($role->id);

            $this->audit->record(
                event: 'membership.role_removed',
                actor: $actor,
                subject: $membership,
                alliance: $alliance,
                metadata: [
                    'role_id' => $role->id,
                    'role_key' => $role->key,
                    'user_id' => $membership->user_id,
                ],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'event_type' => 'membership.role_removed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.role_removed:'.$membership->id.':'.$role->id.':'.now()->format('Uu'),
                'payload' => [
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'user_id' => $membership->user_id,
                    'role_id' => $role->id,
                    'role_key' => $role->key,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh();
        });
    }
}
