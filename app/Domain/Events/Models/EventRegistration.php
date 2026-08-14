<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventRegistration extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'player_id', 'status', 'waitlist_position',
        'registered_by_player_id', 'registered_at',
        'cancelled_by_player_id', 'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventRegistrationStatus::class,
            'waitlist_position' => 'integer',
            'registered_at' => 'datetime',
            'cancelled_at' => 'datetime',
        ];
    }

    public function occurrence(): BelongsTo { return $this->belongsTo(EventOccurrence::class); }
    public function player(): BelongsTo { return $this->belongsTo(Player::class); }
    public function registeredByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'registered_by_player_id'); }
    public function cancelledByPlayer(): BelongsTo { return $this->belongsTo(Player::class, 'cancelled_by_player_id'); }
}
