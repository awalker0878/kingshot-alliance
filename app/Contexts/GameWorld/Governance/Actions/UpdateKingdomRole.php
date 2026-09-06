<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
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

            $keys = collect($permissionKeys)->map('strval')->unique()->values();
            $actorFacts = $this->authorityFacts->findCurrent($actorPlayerId, $kingdomId);
            $effective = $actorFacts === null ? [] : $actorFacts->permissionKeys;
            foreach ($keys as $key) {
                if (! in_array($key, $effective, true)) {
                    throw ValidationException::withMessages(['permissions' => "You cannot delegate permission [{$key}] that the active Player does not hold."]);
                }
            }
            $permissions = Permission::query()->whereIn('key', $keys)->whereNotNull('owner_key')->get()->keyBy('key');
            if ($permissions->count() !== $keys->count()) {
                throw ValidationException::withMessages(['permissions' => 'Every Kingdom-role permission must be a recognized provisioned permission with an owning context.']);
            }

            $before = $role->permissions()->pluck('permissions.key')->map('strval')->sort()->values()->all();
            $after = $keys->sort()->values()->all();
            $role->forceFill(['name' => $name, 'description' => $description === null ? null : trim($description)])->save();
            $role->permissions()->sync($keys->map(static fn (string $key): string => (string) $permissions->get($key)->id)->all());

            $metadata = [
                'kingdom_id' => $kingdomId,
                'role_id' => $roleId,
                'role_key' => (string) $role->key,
                'permission_added' => array_values(array_diff($after, $before)),
                'permission_removed' => array_values(array_diff($before, $after)),
                'affected_players' => $role->assignments()->effective()->distinct('player_id')->count('player_id'),
            ];
            $this->audit->record('kingdom.role_updated', $context->actor, $role, null, $metadata);
            $this->outbox->record('kingdom.role_updated', null, $role, $metadata);
        });
    }
}
