<?php

declare(strict_types=1);

namespace App\Domain\Authorization\Models;

use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $kingdom_id
 * @property string $player_id
 * @property string $kingdom_role_id
 * @property \Illuminate\Support\Carbon|null $created_at
 * @property-read Kingdom $kingdom
 * @property-read Player $player
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
    ];

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<KingdomRole, $this> */
    public function role(): BelongsTo
    {
        return $this->belongsTo(KingdomRole::class, 'kingdom_role_id');
    }
}
