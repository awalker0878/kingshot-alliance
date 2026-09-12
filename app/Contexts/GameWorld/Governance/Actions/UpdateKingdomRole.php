<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleDelegation;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleInput;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateKingdomRole
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private KingdomRoleDelegation $delegation,
        private KingdomRoleInput $input,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param array<mixed> $permissionKeys */
    public function handle(string $actorPlayerId, string $kingdomId, string $roleId, string $name, ?string $description, array $permissionKeys): void
    {
        $input = $this->input->definition($name, $description, $permissionKeys);
        ['name' => $name, 'description' => $description, 'permissions' => $permissionKeys] = $input;

        DB::transaction(function () use ($actorPlayerId, $kingdomId, $roleId, $name, $description, $permissionKeys): void {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $role = KingdomRole::query()->whereKey($roleId)->where('kingdom_id', $kingdomId)->lockForUpdate()->firstOrFail();
            if ($role->is_system) {
                throw ValidationException::withMessages(['role' => 'System Kingdom roles are managed by their owning policy provisioners.']);
            }
            if ($role->archived_at !== null) {
                throw ValidationException::withMessages(['role' => 'Archived Kingdom roles cannot be edited.']);
            }
            $keys = $permissionKeys;
            $permissionIds = $this->delegation->permissionIds($actorPlayerId, $kingdomId, $keys);

            $before = $role->permissions()->pluck('permissions.key')->map('strval')->sort()->values()->all();
            $after = $keys;
            sort($after);
            $role->forceFill(['name' => $name, 'description' => $description === null ? null : trim($description)])->save();
            $role->permissions()->sync($permissionIds);

            $metadata = [
                'kingdom_id' => $kingdomId,
                'role_id' => $roleId,
                'role_key' => (string) $role->key,
                'permission_added' => array_values(array_diff($after, $before)),
                'permission_removed' => array_values(array_diff($before, $after)),
                'affected_players' => KingdomRoleAssignment::query()->effective()->where('kingdom_role_id', $roleId)->distinct('player_id')->count('player_id'),
            ];
            $this->audit->record('kingdom.role_updated', $context->actor, $role, null, $metadata);
            $this->outbox->record('kingdom.role_updated', null, $role, $metadata);
        });
    }
}
