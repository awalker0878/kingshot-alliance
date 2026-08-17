<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventRecurrencePolicy;
use App\Contexts\Operations\Events\Enums\EventScheduleSource;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Enums\RecurrenceFrequency;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $event_type_id
 * @property EventScope $scope
 * @property string $view_permission_key
 * @property string $create_permission_key
 * @property string $manage_permission_key
 * @property int|null $default_duration_minutes
 * @property int|null $default_capacity
 * @property EventScheduleSource $schedule_source
 * @property EventRecurrencePolicy $recurrence_policy
 * @property RecurrenceFrequency $default_recurrence_frequency
 * @property int $default_recurrence_interval
 * @property int|null $minimum_repeat_interval_minutes
 * @property int|null $default_registration_opens_minutes_before
 * @property int|null $default_registration_closes_minutes_before
 * @property string|null $default_instructions_key
 * @property array<string, mixed>|null $default_settings
 * @property string|null $result_score_label_key
 * @property string|null $result_score_unit
 * @property bool $result_score_higher_is_better
 * @property bool $is_active
 * @property int $sort_order
 * @property-read EventType $eventType
 * @property-read Collection<int, EventTypeCapability> $capabilities
 * @property-read Collection<int, EventMetricDefinition> $metricDefinitions
 */
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

    public function defaultRecurrenceFrequencyEnum(): RecurrenceFrequency
    {
        $value = $this->getAttribute('default_recurrence_frequency');

        return $value instanceof RecurrenceFrequency ? $value : RecurrenceFrequency::from((string) $value);
    }

    public function allowsRecurrence(): bool
    {
        return $this->recurrencePolicyEnum()->allowsRecurrence();
    }
}
