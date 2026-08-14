<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Models\KingdomRole;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Kingdoms\Enums\KingdomStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $number
 * @property KingdomStatus $status
 */
final class Kingdom extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'number',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'number' => 'integer',
            'status' => KingdomStatus::class,
        ];
    }

    /** @return HasMany<Alliance, $this> */
    public function alliances(): HasMany
    {
        return $this->hasMany(Alliance::class);
    }

    /** @return HasMany<KingdomAlliance, $this> */
    public function kingdomAlliances(): HasMany
    {
        return $this->hasMany(KingdomAlliance::class);
    }

    /** @return HasMany<KingdomRole, $this> */
    public function authorizationRoles(): HasMany
    {
        return $this->hasMany(KingdomRole::class);
    }

    /** @return HasMany<KingdomRoleAssignment, $this> */
    public function roleAssignments(): HasMany
    {
        return $this->hasMany(KingdomRoleAssignment::class);
    }
}
