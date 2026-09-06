<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Database\Eloquent\Builder;

final readonly class KingdomGovernanceProjectionQuery
{
    public function __construct(private PlayerReferenceQuery $players) {}

    /** @return array{roles:list<array<string,mixed>>,assignments:list<array<string,mixed>>,permissions:list<array<string,mixed>>} */
    public function forKingdom(string $kingdomId): array
    {
        $roles = KingdomRole::query()->where('kingdom_id', $kingdomId)->with('permissions')->orderByDesc('is_system')->orderBy('name')->get();
        $assignmentRows = KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)->whereNull('revoked_at')->with('role')->orderBy('created_at')->get();
        $refs = $this->players->byIds($assignmentRows->pluck('player_id')->map('strval')->all());

        $roleRows = $roles->map(static fn (KingdomRole $role): array => [
            'id' => (string) $role->id,
            'key' => (string) $role->key,
            'name' => (string) $role->name,
            'description' => $role->description,
            'isSystem' => (bool) $role->is_system,
            'archivedAt' => $role->archived_at?->toIso8601String(),
            'permissions' => array_values($role->permissions->map(static fn (Permission $permission): array => ['key' => (string) $permission->key, 'owner' => (string) ($permission->owner_key ?? 'unowned'), 'description' => (string) $permission->description])->sortBy('key')->values()->all()),
            'effectiveAssignmentCount' => (int) KingdomRoleAssignment::query()->effective()->where('kingdom_role_id', (string) $role->id)->distinct('player_id')->count('player_id'),
        ])->values()->all();

        $assignmentProjection = $assignmentRows->map(static function (KingdomRoleAssignment $assignment) use ($refs): array {
            $playerId = (string) $assignment->player_id;
            $playerName = isset($refs[$playerId]) ? $refs[$playerId]->currentName : 'Unknown Governor';

            return [
                'id' => (string) $assignment->id,
                'playerId' => $playerId,
                'playerName' => $playerName,
                'roleId' => (string) $assignment->kingdom_role_id,
                'roleKey' => (string) $assignment->role->key,
                'roleName' => (string) $assignment->role->name,
                'effective' => $assignment->isEffectiveAt(),
                'effectiveFrom' => $assignment->effective_from?->toIso8601String(),
                'expiresAt' => $assignment->expires_at?->toIso8601String(),
                'reason' => $assignment->reason,
            ];
        })->values()->all();

        $permissionRows = Permission::query()->whereNotNull('owner_key')->orderBy('owner_key')->orderBy('key')->get()->map(static fn (Permission $permission): array => ['key' => (string) $permission->key, 'owner' => (string) $permission->owner_key, 'description' => (string) $permission->description])->values()->all();

        return [
            'roles' => array_values($roleRows),
            'assignments' => array_values($assignmentProjection),
            'permissions' => array_values($permissionRows),
        ];
    }

    /** @return list<array{playerId:string,playerName:string,roles:list<string>}> */
    public function holders(string $kingdomId, string $permissionKey): array
    {
        $rows = KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)
            ->whereHas('role.permissions', static fn (Builder $query) => $query->where('permissions.key', $permissionKey))
            ->with('role.permissions')->get();
        $refs = $this->players->byIds($rows->pluck('player_id')->map('strval')->all());
        /** @var array<string, array{playerId:string,playerName:string,roles:list<string>}> $grouped */
        $grouped = [];
        foreach ($rows as $row) {
            $playerId = (string) $row->player_id;
            $playerName = isset($refs[$playerId]) ? $refs[$playerId]->currentName : 'Unknown Governor';
            $grouped[$playerId] ??= ['playerId' => $playerId, 'playerName' => $playerName, 'roles' => []];
            $grouped[$playerId]['roles'][] = (string) $row->role->name;
            $grouped[$playerId]['roles'] = array_values(array_unique($grouped[$playerId]['roles']));
        }

        return array_values($grouped);
    }
}
