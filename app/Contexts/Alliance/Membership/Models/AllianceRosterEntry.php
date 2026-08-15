<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $player_id
 * @property string $observed_name
 * @property string|null $game_role
 * @property RosterState $state
 * @property Carbon|null $joined_at
 * @property Carbon|null $left_at
 * @property string|null $manager_notes
 * @property Carbon|null $last_observed_at
 * @property string $source
 * @property-read Player $player
 */
final class AllianceRosterEntry extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'player_id',
        'observed_name',
        'game_role',
        'state',
        'joined_at',
        'left_at',
        'manager_notes',
        'last_observed_at',
        'source',
    ];

    protected function casts(): array
    {
        return [
            'state' => RosterState::class,
            'joined_at' => 'date',
            'left_at' => 'datetime',
            'last_observed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

}
