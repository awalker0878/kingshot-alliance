<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Models;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventRegistrationStatus $status
 * @property Carbon|null $registered_at
 * @property Carbon|null $cancelled_at
 * @property-read EventOccurrence $occurrence
 * @property-read Player $player
 * @property-read Player|null $registeredByPlayer
 * @property-read Player|null $cancelledByPlayer
 */
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

    public function statusEnum(): EventRegistrationStatus
    {
        return EventRegistrationStatus::from((string) $this->getRawOriginal('status'));
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function registeredByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'registered_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function cancelledByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'cancelled_by_player_id');
    }
}
