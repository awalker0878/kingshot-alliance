<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $kingdom_id
 * @property string $player_id
 * @property string $kingdom_role_id
 * @property Carbon|null $created_at
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
