<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventRecurrencePolicy;
use App\Domain\Events\Enums\EventScheduleSource;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Enums\RecurrenceFrequency;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventTypeScope extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_type_id',
        'scope',
        'view_permission_key',
        'create_permission_key',
        'manage_permission_key',
        'default_duration_minutes',
        'default_capacity',
        'schedule_source',
        'recurrence_policy',
        'default_recurrence_frequency',
        'default_recurrence_interval',
        'minimum_repeat_interval_minutes',
        'default_registration_opens_minutes_before',
        'default_registration_closes_minutes_before',
        'default_instructions_key',
        'default_settings',
        'result_score_label_key',
        'result_score_unit',
        'result_score_higher_is_better',
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scope' => EventScope::class,
            'default_duration_minutes' => 'integer',
            'default_capacity' => 'integer',
            'schedule_source' => EventScheduleSource::class,
            'recurrence_policy' => EventRecurrencePolicy::class,
            'default_recurrence_frequency' => RecurrenceFrequency::class,
            'default_recurrence_interval' => 'integer',
            'minimum_repeat_interval_minutes' => 'integer',
            'default_registration_opens_minutes_before' => 'integer',
            'default_registration_closes_minutes_before' => 'integer',
            'default_settings' => 'array',
            'result_score_higher_is_better' => 'boolean',
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }

    /** @return HasMany<EventTypeCapability, $this> */
    public function capabilities(): HasMany
    {
        return $this->hasMany(EventTypeCapability::class);
    }

    /** @return HasMany<EventMetricDefinition, $this> */
    public function metricDefinitions(): HasMany
    {
        return $this->hasMany(EventMetricDefinition::class)
            ->orderBy('subject')
            ->orderBy('sort_order')
            ->orderBy('key');
    }

    public function allowsRecurrence(): bool
    {
        return $this->recurrence_policy->allowsRecurrence();
    }
}
