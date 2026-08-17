<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Enums\EventResponseSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property EventResponseChoice $response
 * @property EventResponseSource $source
 * @property Carbon|null $available_from
 * @property Carbon|null $available_until
 * @property Carbon|null $responded_at
 * @property-read EventOccurrence $occurrence
 */
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

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class);
    }
}
