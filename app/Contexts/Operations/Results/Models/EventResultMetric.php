<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class EventResultMetric extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'event_result_id',
        'metric_definition_id',
        'dimension_key',
        'value',
        'source',
        'recorded_by_player_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'decimal:4',
            'source' => EventMetricSource::class,
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventResult, $this> */
    public function result(): BelongsTo
    {
        return $this->belongsTo(EventResult::class, 'event_result_id');
    }

    /** @return BelongsTo<EventMetricDefinition, $this> */
    public function definition(): BelongsTo
    {
        return $this->belongsTo(EventMetricDefinition::class, 'metric_definition_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function recordedByPlayer(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'recorded_by_player_id');
    }
}
