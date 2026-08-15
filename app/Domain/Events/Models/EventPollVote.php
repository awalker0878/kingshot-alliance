<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

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

    public function poll(): BelongsTo
    {
        return $this->belongsTo(EventPoll::class, 'poll_id');
    }

    public function option(): BelongsTo
    {
        return $this->belongsTo(EventPollOption::class, 'option_id');
    }

    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'player_id');
    }

    public function castByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'cast_by_player_id');
    }
}
