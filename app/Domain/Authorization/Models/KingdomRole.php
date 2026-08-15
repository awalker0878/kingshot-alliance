<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Models\Kingdom;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class KingdomRole extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_id',
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

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return BelongsToMany<Permission, $this> */
    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'kingdom_role_permissions');
    }

    /** @return HasMany<KingdomRoleAssignment, $this> */
    public function assignments(): HasMany
    {
        return $this->hasMany(KingdomRoleAssignment::class);
    }

    /** @return BelongsToMany<Player, $this> */
    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'kingdom_role_assignments', 'kingdom_role_id', 'player_id')
            ->withPivot(['kingdom_id'])
            ->withTimestamps();
    }
}
