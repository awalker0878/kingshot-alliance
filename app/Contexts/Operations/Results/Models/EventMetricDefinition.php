<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use App\Contexts\Operations\EventCore\Models\EventTypeScope;
use App\Contexts\Operations\Results\Enums\EventMetricAggregation;
use App\Contexts\Operations\Results\Enums\EventMetricSubject;
use App\Contexts\Operations\Results\Enums\EventMetricValueType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property EventMetricSubject $subject
 * @property EventMetricValueType $value_type
 * @property EventMetricAggregation $aggregation
 */
final class EventMetricDefinition extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_type_scope_id',
        'key',
        'subject',
        'label_key',
        'unit',
        'value_type',
        'aggregation',
        'dimension_kind',
        'is_primary',
        'is_contribution_metric',
        'higher_is_better',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'subject' => EventMetricSubject::class,
            'value_type' => EventMetricValueType::class,
            'aggregation' => EventMetricAggregation::class,
            'is_primary' => 'boolean',
            'is_contribution_metric' => 'boolean',
            'higher_is_better' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    /** @return BelongsTo<EventTypeScope, $this> */
    public function typeScope(): BelongsTo
    {
        return $this->belongsTo(EventTypeScope::class);
    }
}
