<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventOccurrenceStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property EventOccurrenceStatus $status
 * @property-read Event $event
 */
final class EventOccurrence extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_id',
        'starts_at',
        'ends_at',
        'status',
        'settings',
    ];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'status' => EventOccurrenceStatus::class,
            'settings' => 'array',
        ];
    }

    /** @return BelongsTo<Event, $this> */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /** @return HasMany<EventResponse, $this> */
    public function responses(): HasMany
    {
        return $this->hasMany(EventResponse::class, 'occurrence_id');
    }

    /** @return HasMany<EventRegistration, $this> */
    public function registrations(): HasMany
    {
        return $this->hasMany(EventRegistration::class, 'occurrence_id');
    }

    /** @return HasMany<EventAttendance, $this> */
    public function attendance(): HasMany
    {
        return $this->hasMany(EventAttendance::class, 'occurrence_id');
    }

    /** @return HasMany<EventPhase, $this> */
    public function phases(): HasMany
    {
        return $this->hasMany(EventPhase::class, 'occurrence_id')->orderBy('sort_order');
    }

    /** @return HasMany<EventPoll, $this> */
    public function polls(): HasMany
    {
        return $this->hasMany(EventPoll::class, 'occurrence_id')->orderBy('created_at');
    }

    /** @return HasMany<EventRoster, $this> */
    public function rosters(): HasMany
    {
        return $this->hasMany(EventRoster::class, 'occurrence_id')->orderBy('sort_order');
    }

    /** @return HasMany<EventObjective, $this> */
    public function objectives(): HasMany
    {
        return $this->hasMany(EventObjective::class, 'occurrence_id')->orderByDesc('priority')->orderBy('sort_order');
    }

    /** @return HasOne<EventResult, $this> */
    public function result(): HasOne
    {
        return $this->hasOne(EventResult::class, 'occurrence_id');
    }

    /** @return HasMany<EventAllianceResult, $this> */
    public function allianceResults(): HasMany
    {
        return $this->hasMany(EventAllianceResult::class, 'occurrence_id');
    }

    /** @return HasMany<EventPlayerResult, $this> */
    public function playerResults(): HasMany
    {
        return $this->hasMany(EventPlayerResult::class, 'occurrence_id');
    }

    /** @return HasMany<EventPlayerContext, $this> */
    public function playerContexts(): HasMany
    {
        return $this->hasMany(EventPlayerContext::class, 'occurrence_id');
    }
}
