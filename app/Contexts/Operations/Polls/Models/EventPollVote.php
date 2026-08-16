<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Polls\Models;

use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventPollVote extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = ['poll_id', 'option_id', 'player_id', 'cast_by_player_id', 'cast_at'];

    protected function casts(): array
    {
        return ['cast_at' => 'datetime'];
    }

    /** @return BelongsTo<EventPoll, $this> */
    public function poll(): BelongsTo
    {
        return $this->belongsTo(EventPoll::class, 'poll_id');
    }

    /** @return BelongsTo<EventPollOption, $this> */
    public function option(): BelongsTo
    {
        return $this->belongsTo(EventPollOption::class, 'option_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function castByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'cast_by_player_id');
    }
}
