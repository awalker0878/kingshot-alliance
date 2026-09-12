<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Actions;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleDelegation;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleInput;
use App\Contexts\GameWorld\Governance\Services\KingdomWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomRole
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
    public function handle(string $actorPlayerId, string $kingdomId, string $name, ?string $description, array $permissionKeys): string
    {
        $input = $this->input->definition($name, $description, $permissionKeys);
        ['name' => $name, 'key' => $key, 'description' => $description, 'permissions' => $permissionKeys] = $input;

        return DB::transaction(function () use ($actorPlayerId, $kingdomId, $name, $key, $description, $permissionKeys): string {
            $context = $this->writeState->lockExclusiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            if (KingdomRole::query()->where('kingdom_id', $kingdomId)->where('key', $key)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['name' => 'A Kingdom role with this key already exists.']);
            }

            $permissionIds = $this->delegation->permissionIds($actorPlayerId, $kingdomId, $permissionKeys);
            $role = KingdomRole::query()->create([
                'kingdom_id' => $kingdomId,
                'key' => $key,
                'name' => $name,
                'description' => $description === null ? null : trim($description),
                'is_system' => false,
                'archived_at' => null,
            ]);
            $role->permissions()->sync($permissionIds);

            $metadata = ['kingdom_id' => $kingdomId, 'role_id' => (string) $role->id, 'role_key' => $key, 'name' => $name, 'permissions' => $permissionKeys];
            $this->audit->record('kingdom.role_created', $context->actor, $role, null, $metadata);
            $this->outbox->record('kingdom.role_created', null, $role, $metadata);

            return (string) $role->id;
        });
    }
}
