<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Events\Enums\EventStatus;
use App\Domain\Events\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EventStatus $status
 * @property RecurrenceFrequency $recurrence_frequency
 * @property Carbon|null $recurrence_until
 */
final class Event extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'template_id',
        'title',
        'instructions',
        'timezone',
        'duration_minutes',
        'capacity',
        'registration_opens_minutes_before',
        'registration_closes_minutes_before',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_weekdays',
        'recurrence_until',
        'status',
        'created_by_user_id',
        'updated_by_user_id',
    ];

    protected function casts(): array
    {
        return [
            'status' => EventStatus::class,
            'recurrence_frequency' => RecurrenceFrequency::class,
            'recurrence_weekdays' => 'array',
            'recurrence_until' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return HasMany<EventOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }
}
