<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Alliances\Models\Alliance;

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
 * @property Carbon $starts_at
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
        'starts_at',
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
            'starts_at' => 'datetime',
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
