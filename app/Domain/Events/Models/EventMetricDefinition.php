<?php

declare(strict_types=1);

namespace App\Domain\Events\Models;

use App\Domain\Events\Enums\EventMetricAggregation;
use App\Domain\Events\Enums\EventMetricSubject;
use App\Domain\Events\Enums\EventMetricValueType;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

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
        return $this->belongsTo(EventTypeScope::class, 'event_type_scope_id');
    }
}
