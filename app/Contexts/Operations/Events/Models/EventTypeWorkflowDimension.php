<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Models;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventTypeWorkflowDimension extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_type_id',
        'dimension',
    ];

    protected function casts(): array
    {
        return [
            'dimension' => EventWorkflowDimension::class,
        ];
    }

    public function dimensionEnum(): EventWorkflowDimension
    {
        $value = $this->getAttribute('dimension');

        return $value instanceof EventWorkflowDimension
            ? $value
            : EventWorkflowDimension::from((string) $value);
    }

    /** @return BelongsTo<EventType, $this> */
    public function eventType(): BelongsTo
    {
        return $this->belongsTo(EventType::class);
    }
}
