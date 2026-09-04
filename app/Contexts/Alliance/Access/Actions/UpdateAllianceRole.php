<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Shared\Infrastructure\Access\Models\Permission as PermissionModel;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class UpdateAllianceRole
{
    public function __construct(
        private AllianceWriteState $writeState,
        private AllianceAuthorization $authorization,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<AlliancePermission> $permissions */
    public function handle(
        string $allianceId,
        string $actorPlayerId,
        string $roleId,
        string $name,
        array $permissions,
    ): string {
        $name = trim($name);
        if ($name === '') {
            throw ValidationException::withMessages(['name' => 'Role name is required.']);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $roleId, $name, $permissions): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::RoleManage);
            foreach ($permissions as $permission) {
                $this->authorization->authorizeContext($context, $permission);
            }

            $role = Role::query()
                ->whereKey($roleId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($role->is_system) {
                throw ValidationException::withMessages(['role' => 'System specialist roles are immutable.']);
            }
            if ($role->archived_at !== null) {
                throw ValidationException::withMessages(['role' => 'Archived specialist roles cannot be changed.']);
            }

            $beforePermissions = $role->permissions()->pluck('key')->map(static fn ($key): string => (string) $key)->sort()->values()->all();
            $afterPermissions = array_map(static fn (AlliancePermission $permission): string => $permission->value, $permissions);
            sort($afterPermissions);

            $permissionIds = [];
            foreach ($permissions as $permission) {
                $model = PermissionModel::query()->firstOrCreate(
                    ['key' => $permission->value],
                    ['id' => (string) Str::ulid(), 'description' => $permission->value],
                );
                $permissionIds[] = (string) $model->id;
            }

            $beforeName = (string) $role->name;
            $role->forceFill(['name' => $name])->save();
            $role->permissions()->sync($permissionIds);

            if ($beforeName !== $name || $beforePermissions !== $afterPermissions) {
                $metadata = [
                    'role_id' => (string) $role->id,
                    'role_key' => (string) $role->key,
                    'name' => ['from' => $beforeName, 'to' => $name],
                    'permissions' => ['from' => $beforePermissions, 'to' => $afterPermissions],
                ];
                $this->audit->record('alliance.role_updated', $context->actor, $role, $context->alliance, $metadata);
                $this->outbox->record('alliance.role_updated', $allianceId, $role, $metadata);
            }

            return (string) $role->id;
        });
    }
}
