<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Access\Models\Permission;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/** @property Carbon|null $archived_at */
final class KingdomRole extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_id',
        'key',
        'name',
        'description',
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

    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    public function permissions(): BelongsToMany
    {
        return $this->belongsToMany(Permission::class, 'kingdom_role_permissions');
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(KingdomRoleAssignment::class);
    }

    public function players(): BelongsToMany
    {
        return $this->belongsToMany(Player::class, 'kingdom_role_assignments', 'kingdom_role_id', 'player_id')
            ->withPivot(['kingdom_id', 'effective_from', 'expires_at', 'revoked_at'])
            ->withTimestamps();
    }
}
