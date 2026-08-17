<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property EventScope $scope
 * @property EventStatus $status
 * @property EventScheduleSource $schedule_source
 * @property EventRecurrencePolicy $recurrence_policy
 * @property RecurrenceFrequency $recurrence_frequency
 * @property Carbon $starts_at
 * @property Carbon|null $recurrence_until
 * @property-read EventType $eventType
 * @property-read EventTypeScope $typeScope
 */
final class Event extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_type_scope_id',
        'event_type_id',
        'scope',
        'alliance_id',
        'kingdom_id',
        'player_id',
        'template_id',
        'target_display_name',
        'target_secondary_label',
        'title',
        'instructions',
        'timezone',
        'schedule_source',
        'recurrence_policy',
        'minimum_repeat_interval_minutes',
        'starts_at',
        'duration_minutes',
        'capacity',
        'registration_opens_minutes_before',
        'registration_closes_minutes_before',
        'recurrence_frequency',
        'recurrence_interval',
        'recurrence_until',
        'settings',
        'status',
        'created_by_player_id',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'scope' => EventScope::class,
            'status' => EventStatus::class,
            'schedule_source' => EventScheduleSource::class,
            'recurrence_policy' => EventRecurrencePolicy::class,
            'minimum_repeat_interval_minutes' => 'integer',
            'recurrence_frequency' => RecurrenceFrequency::class,
            'starts_at' => 'datetime',
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'registration_opens_minutes_before' => 'integer',
            'registration_closes_minutes_before' => 'integer',
            'recurrence_interval' => 'integer',
            'recurrence_until' => 'datetime',
            'settings' => 'array',
        ];
    }

    public function scopeEnum(): EventScope
    {
        return EventScope::from((string) $this->getRawOriginal('scope'));
    }

    /** @return BelongsTo<EventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /** @return BelongsTo<EventTypeScope, $this> */
    public function typeScope(): BelongsTo
    {
        return $this->belongsTo(EventTypeScope::class, 'event_type_scope_id');
    }

    /** @return BelongsTo<EventTemplate, $this> */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EventTemplate::class);
    }

    /** @return HasMany<EventOccurrence, $this> */
    public function occurrences(): HasMany
    {
        return $this->hasMany(EventOccurrence::class);
    }

    /** @return HasMany<EventReminderRule, $this> */
    public function reminderRules(): HasMany
    {
        return $this->hasMany(EventReminderRule::class);
    }
}
