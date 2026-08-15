<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventTemplate extends Model
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
        'name',
        'instructions',
        'timezone',
        'schedule_source',
        'recurrence_policy',
        'minimum_repeat_interval_minutes',
        'duration_minutes',
        'capacity',
        'registration_opens_minutes_before',
        'registration_closes_minutes_before',
        'recurrence_frequency',
        'recurrence_interval',
        'settings',
        'is_active',
        'created_by_player_id',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'scope' => EventScope::class,
            'schedule_source' => EventScheduleSource::class,
            'recurrence_policy' => EventRecurrencePolicy::class,
            'minimum_repeat_interval_minutes' => 'integer',
            'recurrence_frequency' => RecurrenceFrequency::class,
            'duration_minutes' => 'integer',
            'capacity' => 'integer',
            'registration_opens_minutes_before' => 'integer',
            'registration_closes_minutes_before' => 'integer',
            'recurrence_interval' => 'integer',
            'settings' => 'array',
            'is_active' => 'boolean',
        ];
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

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function player(): BelongsTo
    {
        return $this->belongsTo(Player::class);
    }

    /** @return BelongsTo<Player, $this> */
    public function createdByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function updatedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'updated_by_player_id');
    }

    public function scopeEnum(): EventScope
    {
        $value = $this->getAttribute('scope');

        return $value instanceof EventScope ? $value : EventScope::from((string) $value);
    }

    public function scheduleSourceEnum(): EventScheduleSource
    {
        $value = $this->getAttribute('schedule_source');

        return $value instanceof EventScheduleSource ? $value : EventScheduleSource::from((string) $value);
    }

    public function recurrencePolicyEnum(): EventRecurrencePolicy
    {
        $value = $this->getAttribute('recurrence_policy');

        return $value instanceof EventRecurrencePolicy ? $value : EventRecurrencePolicy::from((string) $value);
    }

    public function recurrenceFrequencyEnum(): RecurrenceFrequency
    {
        $value = $this->getAttribute('recurrence_frequency');

        return $value instanceof RecurrenceFrequency ? $value : RecurrenceFrequency::from((string) $value);
    }
}
