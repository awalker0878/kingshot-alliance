<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\Access\Models\Permission;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateKingdomRole
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private KingdomAuthorityFactsQuery $authorityFacts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $permissionKeys */
    public function handle(string $actorPlayerId, string $kingdomId, string $roleId, string $name, ?string $description, array $permissionKeys): void
    {
        DB::transaction(function () use ($actorPlayerId, $kingdomId, $roleId, $name, $description, $permissionKeys): void {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            $role = KingdomRole::query()->whereKey($roleId)->where('kingdom_id', $kingdomId)->lockForUpdate()->firstOrFail();
            if ($role->is_system) {
                throw ValidationException::withMessages(['role' => 'System Kingdom roles are managed by their owning policy provisioners.']);
            }
            if ($role->archived_at !== null) {
                throw ValidationException::withMessages(['role' => 'Archived Kingdom roles cannot be edited.']);
            }
            $name = trim($name);
            if ($name === '') {
                throw ValidationException::withMessages(['name' => 'Role name is required.']);
            }

            $keys = array_values(array_unique(array_map('strval', $permissionKeys)));
            $actorFacts = $this->authorityFacts->findCurrent($actorPlayerId, $kingdomId);
            $effective = $actorFacts === null ? [] : $actorFacts->permissionKeysObservedAtRead;
            foreach ($keys as $key) {
                if (! in_array($key, $effective, true)) {
                    throw ValidationException::withMessages(['permissions' => "You cannot delegate permission [{$key}] that the active Player does not hold."]);
                }
            }
            $permissions = Permission::query()->whereIn('key', $keys)->whereNotNull('owner_key')->get()->keyBy('key');
            $permissionIds = [];
            foreach ($keys as $key) {
                $permission = $permissions->get($key);
                if (! $permission instanceof Permission) {
                    throw ValidationException::withMessages(['permissions' => 'Every Kingdom-role permission must be a recognized provisioned permission with an owning context.']);
                }
                $permissionIds[] = (string) $permission->id;
            }

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
