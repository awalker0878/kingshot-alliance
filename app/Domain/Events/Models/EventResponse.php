<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventResponseChoice;
use App\Domain\Events\Enums\EventResponseSource;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventResponse extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'player_id', 'response', 'preferred_role', 'preferred_team',
        'available_from', 'available_until', 'note', 'source',
        'responded_by_player_id', 'responded_at',
    ];

    protected function casts(): array
    {
        return [
            'response' => EventResponseChoice::class,
            'source' => EventResponseSource::class,
            'available_from' => 'datetime',
            'available_until' => 'datetime',
            'responded_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function respondedByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'responded_by_player_id'); }
}
