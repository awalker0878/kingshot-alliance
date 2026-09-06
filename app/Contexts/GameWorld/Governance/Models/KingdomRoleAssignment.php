<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\CarbonInterface;

/**
 * @property string $kingdom_id
 * @property string $player_id
 * @property string $kingdom_role_id
 * @property string|null $assigned_by_player_id
 * @property Carbon|null $effective_from
 * @property Carbon|null $expires_at
 * @property string|null $reason
 * @property Carbon|null $revoked_at
 * @property string|null $revoked_by_player_id
 * @property string|null $revocation_reason
 * @property Carbon|null $created_at
 * @property-read KingdomRole $role
 */
final class KingdomRoleAssignment extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'kingdom_id',
        'player_id',
        'kingdom_role_id',
        'assigned_by_player_id',
        'effective_from',
        'expires_at',
        'reason',
        'revoked_at',
        'revoked_by_player_id',
        'revocation_reason',
    ];

    protected function casts(): array
    {
        return [
            'effective_from' => 'datetime',
            'expires_at' => 'datetime',
            'revoked_at' => 'datetime',
        ];
    }

    public function scopeEffective(Builder $query, ?CarbonInterface $at = null): Builder
    {
        $at ??= now();

        return $query
            ->whereNull('revoked_at')
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('effective_from')->orWhere('effective_from', '<=', $at);
            })
            ->where(function (Builder $builder) use ($at): void {
                $builder->whereNull('expires_at')->orWhere('expires_at', '>', $at);
            })
            ->whereHas('role', static fn (Builder $builder) => $builder->whereNull('archived_at'));
    }

    public function isEffectiveAt(?CarbonInterface $at = null): bool
    {
        $at ??= now();

        return $this->revoked_at === null
            && ($this->effective_from === null || $this->effective_from->lte($at))
            && ($this->expires_at === null || $this->expires_at->gt($at))
            && $this->role->archived_at === null;
    }

    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    public function role(): BelongsTo
    {
        return $this->belongsTo(KingdomRole::class, 'kingdom_role_id');
    }
}
