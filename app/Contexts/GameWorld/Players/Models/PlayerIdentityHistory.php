<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Players\Models;

use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * Immutable temporal identity/ownership snapshot for a Player.
 *
 * @property string $player_id
 * @property int|null $user_id
 * @property string $kingdom_id
 * @property string $name
 * @property string|null $game_player_id
 * @property Carbon $valid_from
 * @property Carbon|null $valid_to
 * @property PlayerIdentitySource $source_type
 * @property string|null $source_reference
 * @property Carbon|null $observed_at
 * @property int|null $confidence_basis_points
 * @property string|null $reason
 */
final class PlayerIdentityHistory extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'player_identity_history';

    protected $fillable = [
        'player_id',
        'user_id',
        'kingdom_id',
        'name',
        'game_player_id',
        'valid_from',
        'valid_to',
        'source_type',
        'source_reference',
        'observed_at',
        'confidence_basis_points',
        'reason',
    ];

    protected function casts(): array
    {
        return [
            'user_id' => 'integer',
            'valid_from' => 'immutable_datetime',
            'valid_to' => 'immutable_datetime',
            'source_type' => PlayerIdentitySource::class,
            'observed_at' => 'immutable_datetime',
            'confidence_basis_points' => 'integer',
        ];
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }
}
