<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomGovernance\Queries;

use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class KingdomGovernanceProjectionQuery
{
    public function __construct(private GovernanceCataloguePage $pages, private KingdomAuthorization $authorization,
        private KingdomAuthorityFactsQuery $authorityFacts) {}

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function roles(string $actorId, string $kingdomId, ?string $cursor = null): array
    {
        $this->authorize($actorId, $kingdomId);
        $query = KingdomRole::query()->where('kingdom_id', $kingdomId)
            ->with(['permissions' => static fn ($query) => $query->orderBy('key')->limit(501)])
            ->select(['id', 'kingdom_id', 'key', 'name', 'description', 'is_system', 'archived_at'])
            ->selectSub($this->effective($kingdomId)->whereColumn('kingdom_role_id', 'kingdom_roles.id')
                ->selectRaw('count(distinct player_id)'), 'effective_assignment_count');
        $page = $this->pages->slice($query, $this->scope($actorId, $kingdomId, 'roles'), $cursor);
        $page['items'] = array_map(static function (KingdomRole $role): array {
            if ($role->permissions->count() > 500) {
                throw new LogicException('The Kingdom role permission registry exceeds its supported projection bound.');
            }

            return ['id' => (string) $role->id, 'key' => $role->key, 'name' => $role->name, 'description' => $role->description,
                'isSystem' => $role->is_system, 'archivedAt' => $role->archived_at?->toIso8601String(),
                'permissions' => array_values($role->permissions->map(static fn (Permission $permission): array => [
                    'key' => $permission->key, 'owner' => $permission->owner_key, 'description' => $permission->description,
                ])->all()), 'effectiveAssignmentCount' => (int) $role->getAttribute('effective_assignment_count')];
        }, $page['items']);

        return $page;
    }

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function assignments(string $actorId, string $kingdomId, ?string $cursor = null, string $search = '', ?string $roleId = null, ?string $playerId = null, ?string $permission = null): array
    {
        $this->authorize($actorId, $kingdomId);
        $search = $this->search($search);
        $this->optionalId($roleId);
        $this->optionalId($playerId);
        $this->permissionKey($permission);
        $query = KingdomRoleAssignment::query()->where('kingdom_id', $kingdomId)->whereNull('revoked_at')
            ->with(['role:id,key,name,archived_at', 'player:id,current_name,game_player_id,current_kingdom_id,canonical_player_id'])
            ->select(['id', 'kingdom_id', 'player_id', 'kingdom_role_id', 'effective_from', 'expires_at', 'revoked_at', 'created_at'])
            ->selectRaw('case when assigned_by_player_id is not null then left(reason, 500) else null end as reason');
        if ($roleId !== null) {
            $query->where('kingdom_role_id', $roleId);
        }
        if ($playerId !== null) {
            $query->where('player_id', $playerId);
        }
        if ($search !== '') {
            $query->whereHas('player', fn ($query) => $this->playerSearch($query->getQuery(), $search));
        }
        if ($permission !== null) {
            $query->effective()->whereHas('role.permissions', static fn ($query) => $query->where('permissions.key', $permission));
        }
        $scope = $this->scope($actorId, $kingdomId, 'assignments', [$search, $roleId, $playerId, $permission]);
        $page = $this->pages->slice($query, $scope, $cursor);
        $page['items'] = array_map(static function (KingdomRoleAssignment $assignment) use ($kingdomId): array {
            $player = $assignment->player;
            $current = $player->canonical_player_id === null && $player->current_kingdom_id === $kingdomId;
            $state = $current && $assignment->isEffectiveAt() ? 'effective'
                : ($current && ($assignment->effective_from?->isFuture() ?? false) ? 'scheduled' : 'expired');

            return ['id' => (string) $assignment->id,
                'player' => ['id' => (string) $player->id, 'name' => $player->current_name, 'gamePlayerId' => $player->game_player_id],
                'role' => ['id' => (string) $assignment->role->id, 'key' => $assignment->role->key, 'name' => $assignment->role->name],
                'state' => $state, 'effectiveFrom' => $assignment->effective_from?->toIso8601String(),
                'expiresAt' => $assignment->expires_at?->toIso8601String(), 'reason' => $assignment->reason,
                'assignedAt' => $assignment->created_at?->toIso8601String()];
        }, $page['items']);

        return $page;
    }

    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int} */
    public function holders(string $actorId, string $kingdomId, string $permission, ?string $cursor = null): array
    {
        $this->authorize($actorId, $kingdomId);
        $this->permissionKey($permission);
        $assignments = $this->effective($kingdomId)
            ->whereHas('role.permissions', static fn ($query) => $query->where('permissions.key', $permission));
        $query = Player::query()->where('current_kingdom_id', $kingdomId)->whereNull('canonical_player_id')
            ->whereIn('id', (clone $assignments)->select('player_id'))->select(['id', 'current_name'])
            ->selectSub((clone $assignments)->whereColumn('player_id', 'players.id')->selectRaw('count(distinct kingdom_role_id)'), 'role_count');
        $page = $this->pages->slice($query, $this->scope($actorId, $kingdomId, 'holders', [$permission]), $cursor);
        $page['items'] = array_map(static fn (Player $player): array => ['playerId' => (string) $player->id,
            'playerName' => $player->current_name, 'roleCount' => (int) $player->getAttribute('role_count')], $page['items']);

        return $page;
    }

    /** @return array{page:array{items:list<array{id:string,name:string}>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int},total:int,selected:?array{id:string,name:string}} */
    public function choices(string $actorId, string $kingdomId, string $kind, string $search = '', ?string $cursor = null, ?string $selectedId = null): array
    {
        $this->authorize($actorId, $kingdomId);
        $search = $this->search($search);
        $this->optionalId($selectedId);
        if (! in_array($kind, ['players', 'roles'], true)) {
            throw ValidationException::withMessages(['choices' => 'Choose a supported Governance catalogue.']);
        }
        $scope = $this->scope($actorId, $kingdomId, 'choices-'.$kind, [$search]);
        if ($kind === 'players') {
            $players = Player::query()->where('current_kingdom_id', $kingdomId)->whereNull('canonical_player_id')->select(['id', 'current_name', 'game_player_id']);
            $selected = $selectedId === null ? null : (clone $players)->whereKey($selectedId)->first();
            if ($search !== '') {
                $this->playerSearch($players->getQuery(), $search);
            }

            return $this->choicePage($players, $scope, $cursor, $selected);
        }
        $roles = KingdomRole::query()->where('kingdom_id', $kingdomId)->whereNull('archived_at')->select(['id', 'name', 'key']);
        $selected = $selectedId === null ? null : (clone $roles)->whereKey($selectedId)->first();
        if ($search !== '') {
            $roles->where(static fn ($query) => $query->where('name', 'ilike', self::pattern($search))->orWhere('key', 'ilike', self::pattern($search)));
        }

        return $this->choicePage($roles, $scope, $cursor, $selected);
    }

    /**
     * @template T of Model
     *
     * @param  Builder<T>  $query
     * @return array{page:array{items:list<array{id:string,name:string}>,nextCursor:?string,hasMore:bool,pageSize:int,isFirstPage:bool,total:int},total:int,selected:?array{id:string,name:string}}
     */
    private function choicePage(Builder $query, string $scope, ?string $cursor, ?Model $selected): array
    {
        $page = $this->pages->slice($query, $scope, $cursor);
        $choice = static fn (Model $row): array => ['id' => (string) $row->getKey(),
            'name' => $row instanceof Player ? $row->current_name.($row->game_player_id === null ? '' : ' · '.$row->game_player_id) : (string) $row->getAttribute('name')];
        $page['items'] = array_map($choice, $page['items']);

        return ['page' => $page, 'total' => $page['total'], 'selected' => $selected === null ? null : $choice($selected)];
    }

    /** @return list<array{key:string,owner:?string,description:string}> */
    public function permissions(string $actorId, string $kingdomId, bool $delegatableOnly): array
    {
        $this->authorize($actorId, $kingdomId);
        $query = Permission::query()->whereNotNull('owner_key');
        if ($delegatableOnly) {
            $facts = $this->authorityFacts->findCurrent($actorId, $kingdomId);
            if ($facts === null) {
                throw new AuthorizationException;
            }
            $query->whereIn('key', $facts->permissionKeysObservedAtRead);
        }
        $rows = $query->orderBy('owner_key')->orderBy('key')->limit(501)->get(['key', 'owner_key', 'description']);
        if ($rows->count() > 500) {
            throw new LogicException('The Kingdom permission registry exceeds its supported projection bound.');
        }

        return array_values($rows->map(static fn (Permission $permission): array => ['key' => $permission->key,
            'owner' => $permission->owner_key, 'description' => $permission->description])->all());
    }

    public function authorize(string $actorId, string $kingdomId): void
    {
        if (! $this->authorization->allows($actorId, $kingdomId, KingdomPermission::RoleManage)) {
            throw new AuthorizationException;
        }
    }

    /** @return Builder<KingdomRoleAssignment> */
    private function effective(string $kingdomId): Builder
    {
        return KingdomRoleAssignment::query()->effective()->where('kingdom_id', $kingdomId)
            ->whereHas('player', static fn ($query) => $query->where('current_kingdom_id', $kingdomId)->whereNull('canonical_player_id'));
    }

    private function optionalId(?string $id): void
    {
        if ($id !== null && ! $this->pages->isId($id)) {
            throw ValidationException::withMessages(['filter' => 'The Governance filter identifier is invalid.']);
        }
    }

    private function permissionKey(?string $permission): void
    {
        if ($permission !== null && (strlen($permission) > 100 || ! Permission::query()->whereNotNull('owner_key')->where('key', $permission)->exists())) {
            throw ValidationException::withMessages(['permission' => 'Choose a declared permission.']);
        }
    }

    private function search(string $search): string
    {
        $search = trim($search);
        if (mb_strlen($search) > 160) {
            throw ValidationException::withMessages(['search' => 'Search must be 160 characters or fewer.']);
        }

        return $search;
    }

    private function playerSearch(QueryBuilder $query, string $search): void
    {
        $query->where(static fn ($query) => $query->where('current_name', 'ilike', self::pattern($search))->orWhere('game_player_id', 'ilike', self::pattern($search)));
    }

    private static function pattern(string $search): string
    {
        return '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%';
    }

    /** @param list<?string> $filters */
    private function scope(string $actorId, string $kingdomId, string $kind, array $filters = []): string
    {
        return implode('|', ['kingdom-governance', $actorId, $kingdomId, $kind, hash('sha256', json_encode($filters, JSON_THROW_ON_ERROR))]);
    }
}
