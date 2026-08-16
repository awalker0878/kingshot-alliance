<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
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
            $context = $this->authority->require($actor, $alliance, AlliancePermission::RoleManage);

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
