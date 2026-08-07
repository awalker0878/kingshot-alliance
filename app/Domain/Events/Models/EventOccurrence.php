<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EventOccurrenceStatus $status
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property Carbon|null $registration_opens_at
 * @property Carbon|null $registration_closes_at
 */
final class EventOccurrence extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'event_id',
        'starts_at',
        'ends_at',
        'registration_opens_at',
        'registration_closes_at',
        'capacity',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'registration_opens_at' => 'datetime',
            'registration_closes_at' => 'datetime',
            'status' => EventOccurrenceStatus::class,
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'occurrence_id');
    }
}
