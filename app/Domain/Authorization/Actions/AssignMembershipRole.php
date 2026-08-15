<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Shared\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class AssignMembershipRole
{
    public function __construct(
        private AllianceMutationAuthority $authority,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $membershipId,
        string $roleId,
    ): AllianceMembership {
        return DB::transaction(function () use ($alliance, $actor, $membershipId, $roleId): AllianceMembership {
            $context = $this->authority->require($actor, $alliance, PermissionKey::RoleManage);

            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $context->alliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($membership->status !== MembershipStatus::Active) {
                throw ValidationException::withMessages([
                    'membership' => 'Only active memberships can receive specialist roles.',
                ]);
            }

            $role = Role::query()
                ->where('id', $roleId)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            if ($membership->roles()->where('roles.id', $role->id)->exists()) {
                return $membership->refresh();
            }

            $membership->roles()->attach($role->id, ['alliance_id' => $context->alliance->id]);

            $metadata = [
                'role_id' => $role->id,
                'role_key' => $role->key,
                'player_id' => $membership->player_id,
            ];

            $this->audit->record('membership.role_assigned', $context->actor, $membership, $context->alliance, $metadata);

            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'membership.role_assigned',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.role_assigned:'.$membership->id.':'.$role->id.':'.Str::ulid(),
                'payload' => [
                    'alliance_id' => $context->alliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $membership->player_id,
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
