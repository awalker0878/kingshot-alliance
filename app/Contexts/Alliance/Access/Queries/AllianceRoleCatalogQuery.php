<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Queries;

use App\Contexts\Alliance\Access\Models\Role;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Validation\ValidationException;

final readonly class AllianceRoleCatalogQuery
{
    public const PAGE_SIZE = 25;

    public function __construct(private ScopedCursorCodec $cursors) {}

    /** @return PageSlice<array{id:string,key:string,name:string,system:bool,archivedAt:string|null,permissions:list<string>,memberCount:int}> */
    public function management(string $allianceId, bool $archived = false, string $search = '', ?string $cursor = null): PageSlice
    {
        $rows = $this->query($allianceId, $archived, $search, $cursor)
            ->with('permissions:id,key')
            ->withCount(['memberships' => static function (Builder $memberships) use ($allianceId): void {
                $memberships->where('membership_roles.alliance_id', $allianceId);
            }])->get();
        $items = array_values($rows->take(self::PAGE_SIZE)->map(static fn (Role $role): array => [
            'id' => (string) $role->id,
            'key' => (string) $role->key,
            'name' => (string) $role->name,
            'system' => (bool) $role->is_system,
            'archivedAt' => $role->archived_at?->toIso8601String(),
            'permissions' => array_values($role->permissions->pluck('key')->map(static fn ($key): string => (string) $key)->sort()->all()),
            'memberCount' => (int) $role->getAttribute('memberships_count'),
        ])->all());

        return new PageSlice($items, $this->nextCursor($rows, $allianceId, $archived, $search), self::PAGE_SIZE, $cursor === null || $cursor === '');
    }

    /** @return PageSlice<array{id:string,key:string,name:string,system:bool}> */
    public function options(string $allianceId, string $search = '', ?string $cursor = null): PageSlice
    {
        $rows = $this->query($allianceId, false, $search, $cursor)->get(['id', 'key', 'name', 'is_system']);
        $items = array_values($rows->take(self::PAGE_SIZE)->map(static fn (Role $role): array => [
            'id' => (string) $role->id,
            'key' => (string) $role->key,
            'name' => (string) $role->name,
            'system' => (bool) $role->is_system,
        ])->all());

        return new PageSlice($items, $this->nextCursor($rows, $allianceId, false, $search), self::PAGE_SIZE, $cursor === null || $cursor === '');
    }

    /** @return Builder<Role> */
    private function query(string $allianceId, bool $archived, string $search, ?string $cursor): Builder
    {
        $query = Role::query()->where('alliance_id', $allianceId);
        $archived ? $query->whereNotNull('archived_at') : $query->whereNull('archived_at');
        $search = mb_strtolower(trim($search));
        if ($search !== '') {
            $query->whereRaw('lower(name) LIKE ?', [addcslashes($search, '%_\\').'%']);
        }
        if ($cursor !== null && $cursor !== '') {
            $position = $this->cursors->decode($cursor, $this->scope($allianceId, $archived, $search));
            $key = $position['key'] ?? null;
            if (! is_string($key) || $key === '' || strlen($key) > 64) {
                throw ValidationException::withMessages(['cursor' => 'The role cursor is incomplete.']);
            }
            $query->where('key', '>', $key);
        }

        return $query->orderBy('key')->limit(self::PAGE_SIZE + 1);
    }

    /** @param Collection<int,Role> $rows */
    private function nextCursor(Collection $rows, string $allianceId, bool $archived, string $search): ?string
    {
        if ($rows->count() <= self::PAGE_SIZE) {
            return null;
        }
        $last = $rows->get(self::PAGE_SIZE - 1);

        return $last instanceof Role
            ? $this->cursors->encode($this->scope($allianceId, $archived, $search), ['key' => (string) $last->key])
            : null;
    }

    private function scope(string $allianceId, bool $archived, string $search): string
    {
        return 'alliance-role-catalog:'.$allianceId.':'.($archived ? 'archived' : 'active').':'.hash('sha256', mb_strtolower(trim($search)));
    }
}
