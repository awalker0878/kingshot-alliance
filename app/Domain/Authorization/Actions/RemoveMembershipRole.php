<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class RemoveMembershipRole
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
    ) {}

    public function handle(
        Alliance $alliance,
        Player $actor,
        string $membershipId,
        string $roleId,
    ): AllianceMembership {
        if (! $this->authorization->allows($actor, $alliance, PermissionKey::RoleManage)) {
            throw new AuthorizationException;
        }

        return DB::transaction(function () use ($alliance, $actor, $membershipId, $roleId): AllianceMembership {
            $currentAlliance = Alliance::query()->whereKey($alliance->id)->lockForUpdate()->firstOrFail();
            $lockedActor = Player::query()->whereKey($actor->id)->lockForUpdate()->firstOrFail();
            if (! $this->authorization->allowsForUpdate($lockedActor, $currentAlliance, PermissionKey::RoleManage)) {
                throw new AuthorizationException;
            }

            $membership = AllianceMembership::query()
                ->where('id', $membershipId)
                ->where('alliance_id', $currentAlliance->id)
                ->lockForUpdate()
                ->firstOrFail();

            $role = Role::query()
                ->where('id', $roleId)
                ->where('alliance_id', $currentAlliance->id)
                ->firstOrFail();

            $membership->roles()->detach($role->id);

            $metadata = [
                'role_id' => $role->id,
                'role_key' => $role->key,
                'player_id' => $membership->player_id,
            ];

            $this->audit->record('membership.role_removed', $lockedActor, $membership, $currentAlliance, $metadata);

            OutboxMessage::query()->create([
                'alliance_id' => $currentAlliance->id,
                'partition_key' => 'alliance:'.$currentAlliance->id,
                'event_type' => 'membership.role_removed',
                'aggregate_type' => AllianceMembership::class,
                'aggregate_id' => $membership->id,
                'idempotency_key' => 'membership.role_removed:'.$membership->id.':'.$role->id.':'.now()->format('Uu'),
                'payload' => [
                    'alliance_id' => $currentAlliance->id,
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
