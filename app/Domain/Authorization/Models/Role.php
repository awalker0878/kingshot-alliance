<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Memberships\Models\AllianceMembership;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

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
    ];

    protected function casts(): array
    {
        return [
            'is_system' => 'boolean',
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
