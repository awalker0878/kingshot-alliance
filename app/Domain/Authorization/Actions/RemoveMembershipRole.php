<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Support\Facades\DB;

final readonly class RemoveMembershipRole
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

            $role = Role::query()
                ->where('id', $roleId)
                ->where('alliance_id', $context->alliance->id)
                ->sharedLock()
                ->firstOrFail();

            $membership->roles()->detach($role->id);

            $metadata = [
                'role_id' => $role->id,
                'role_key' => $role->key,
                'player_id' => $membership->player_id,
            ];

            $this->audit->record('membership.role_removed', $context->actor, $membership, $context->alliance, $metadata);

            OutboxMessage::query()->create([
                'alliance_id' => $context->alliance->id,
                'partition_key' => 'alliance:'.$context->alliance->id,
                'event_type' => 'membership.role_removed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.role_removed:'.$membership->id.':'.$role->id.':'.now()->format('Uu'),
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
