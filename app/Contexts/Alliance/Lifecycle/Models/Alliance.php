<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Models;

use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $name
 * @property string $slug
 * @property string $kingdom_id
 * @property string $language
 * @property string $timezone
 * @property AllianceStatus $status
 * @property Carbon|null $suspended_at
 * @property Carbon|null $closed_at
 * @property Carbon|null $deleted_at
 * @property Carbon|null $restored_at
 * @property Carbon|null $retention_until
 * @property string|null $lifecycle_reason
 */
final class Alliance extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['name', 'slug', 'kingdom_id', 'language', 'timezone', 'status', 'suspended_at', 'closed_at', 'deleted_at', 'restored_at', 'retention_until', 'lifecycle_reason'];

    protected function casts(): array
    {
        return ['status' => AllianceStatus::class, 'suspended_at' => 'datetime', 'closed_at' => 'datetime', 'deleted_at' => 'datetime', 'restored_at' => 'datetime', 'retention_until' => 'datetime'];
    }

    /** @return HasMany<AllianceMembership, $this> */
    public function memberships(): HasMany
    {
        return $this->hasMany(AllianceMembership::class);
    }

    /** @return HasMany<Role, $this> */
    public function roles(): HasMany
    {
        return $this->hasMany(Role::class);
    }
}
