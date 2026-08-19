<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
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
}
