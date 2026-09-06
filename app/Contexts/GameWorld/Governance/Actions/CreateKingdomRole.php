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
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class CreateKingdomRole
{
    public function __construct(
        private KingdomWriteState $writeState,
        private KingdomAuthorization $authorization,
        private KingdomAuthorityFactsQuery $authorityFacts,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /** @param list<string> $permissionKeys */
    public function handle(string $actorPlayerId, string $kingdomId, string $name, ?string $description, array $permissionKeys): string
    {
        $name = trim($name);
        $key = Str::slug($name);
        if ($name === '' || $key === '') {
            throw ValidationException::withMessages(['name' => 'Role name is required.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $kingdomId, $name, $key, $description, $permissionKeys): string {
            $context = $this->writeState->lockActiveScope($actorPlayerId, $kingdomId);
            $this->authorization->authorizeContext($context, KingdomPermission::RoleManage);
            if (KingdomRole::query()->where('kingdom_id', $kingdomId)->where('key', $key)->lockForUpdate()->exists()) {
                throw ValidationException::withMessages(['name' => 'A Kingdom role with this key already exists.']);
            }

            $permissionIds = $this->authorizedPermissionIds($actorPlayerId, $kingdomId, $permissionKeys);
            $role = KingdomRole::query()->create([
                'kingdom_id' => $kingdomId,
                'key' => $key,
                'name' => $name,
                'description' => $description === null ? null : trim($description),
                'is_system' => false,
                'archived_at' => null,
            ]);
            $role->permissions()->sync($permissionIds);

            $metadata = ['kingdom_id' => $kingdomId, 'role_id' => (string) $role->id, 'role_key' => $key, 'name' => $name, 'permissions' => array_values($permissionKeys)];
            $this->audit->record('kingdom.role_created', $context->actor, $role, null, $metadata);
            $this->outbox->record('kingdom.role_created', null, $role, $metadata);

            return (string) $role->id;
        });
    }

    /** @param list<string> $permissionKeys @return list<string> */
    private function authorizedPermissionIds(string $actorPlayerId, string $kingdomId, array $permissionKeys): array
    {
        $keys = collect($permissionKeys)->map('strval')->unique()->values();
        if ($keys->isEmpty()) {
            return [];
        }
        $actorFacts = $this->authorityFacts->findCurrent($actorPlayerId, $kingdomId);
        $effective = $actorFacts === null ? [] : $actorFacts->permissionKeysObservedAtRead;
        foreach ($keys as $key) {
            if (! in_array($key, $effective, true)) {
                throw ValidationException::withMessages(['permissions' => "You cannot delegate permission [{$key}] that the active Player does not hold."]);
            }
        }
        $permissions = Permission::query()->whereIn('key', $keys)->whereNotNull('owner_key')->get()->keyBy('key');
        if ($permissions->count() !== $keys->count()) {
            throw ValidationException::withMessages(['permissions' => 'Every Kingdom-role permission must be a recognized provisioned permission with an owning context.']);
        }

        return $keys->map(static fn (string $key): string => (string) $permissions->get($key)->id)->all();
    }
}
