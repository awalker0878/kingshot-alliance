<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Models;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $key
 * @property string $name
 * @property bool $is_system
 * @property Carbon|null $archived_at
 * @property-read Collection<int, Permission> $permissions
 * @property-read Collection<int, AllianceMembership> $memberships
 */
final class Role extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'key',
        'name',
        'is_system',
        'archived_at',
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'role_permissions');
    }

    /** @return BelongsToMany<AllianceMembership, $this> */
    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(AllianceMembership::class, 'membership_roles', 'role_id', 'membership_id');
    }
}
