<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Scope is an application authority/target boundary only. It deliberately does
 * not carry Kingshot mechanics, recurrence rules, capacities or profile truth.
 *
 * @property string $event_type_id
 * @property EventScope $scope
 * @property string $view_permission_key
 * @property string $create_permission_key
 * @property string $manage_permission_key
 * @property bool $is_active
 * @property int $sort_order
 * @property-read EventType $eventType
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
        'is_active',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'scope' => EventScope::class,
            'is_active' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
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
}
