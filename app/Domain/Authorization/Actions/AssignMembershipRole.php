<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AssignMembershipRole
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        User $actor,
        string $membershipId,
        string $roleId,
    ): AllianceMembership {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RoleManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $roleId): AllianceMembership {
            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->status !== MembershipStatus::Active) {
                throw ValidationException::withMessages([
                    'membership' => 'Only active memberships can receive role assignments.',
                ]);
            }

            $role = Role::query()
                ->where('id', $roleId)
                ->where('alliance_id', $alliance->id)
                ->firstOrFail();

            if ($membership->roles()->where('roles.id', $role->id)->exists()) {
                return $membership->refresh();
            }

            $membership->roles()->attach($role->id, [
                'alliance_id' => $alliance->id,
            ]);

            $this->audit->record(
                event: 'membership.role_assigned',
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
                'event_type' => 'membership.role_assigned',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.role_assigned:'.$membership->id.':'.$role->id.':'.Str::ulid(),
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
