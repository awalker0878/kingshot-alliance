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

final readonly class CreateAllianceRole
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
        string $name,
        array $permissions,
    ): string {
        $name = trim($name);
        $key = Str::slug($name);
        if ($name === '' || $key === '') {
            throw ValidationException::withMessages(['name' => 'Role name is required.']);
        }
        if (mb_strlen($name) > 100 || strlen($key) > 64) {
            throw ValidationException::withMessages(['name' => 'Use a shorter role name.']);
        }

        return DB::transaction(function () use ($allianceId, $actorPlayerId, $name, $key, $permissions): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $allianceId);
            $this->authorization->authorizeContext($context, AlliancePermission::RoleManage);
            foreach ($permissions as $permission) {
                $this->authorization->authorizeContext($context, $permission);
            }

            // firstOrCreate uses a savepoint for a competing unique-key insert.
            // An existing winner is validation feedback, never an update target.
            $role = Role::query()->firstOrCreate([
                'alliance_id' => $allianceId,
                'key' => $key,
            ], [
                'name' => $name,
                'is_system' => false,
                'archived_at' => null,
            ]);
            if (! $role->wasRecentlyCreated) {
                throw ValidationException::withMessages(['name' => 'A specialist role with this key already exists.']);
            }

            $permissionIds = [];
            foreach ($permissions as $permission) {
                $model = PermissionModel::query()->firstOrCreate(
                    ['key' => $permission->value],
                    ['id' => (string) Str::ulid(), 'description' => $permission->value],
                );
                $permissionIds[] = (string) $model->id;
            }
            $role->permissions()->sync($permissionIds);

            $metadata = [
                'role_id' => (string) $role->id,
                'role_key' => $role->key,
                'name' => $role->name,
                'permissions' => array_map(static fn (AlliancePermission $permission): string => $permission->value, $permissions),
            ];
            $this->audit->record('alliance.role_created', $context->actor, $role, $context->alliance, $metadata);
            $this->outbox->record('alliance.role_created', $allianceId, $role, $metadata);

            return (string) $role->id;
        });
    }
}
