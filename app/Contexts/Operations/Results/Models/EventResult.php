<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use App\Contexts\Operations\Events\Models\EventOccurrence;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class EventResult extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id',
        'outcome',
        'score',
        'opponent_score',
        'rank',
        'notes',
        'recorded_by_player_id',
        'recorded_at',
    ];

    protected function casts(): array
    {
        return [
            'score' => 'integer',
            'opponent_score' => 'integer',
            'rank' => 'integer',
            'recorded_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<EventOccurrence, $this> */
    public function occurrence(): BelongsTo
    {
        return $this->belongsTo(EventOccurrence::class, 'occurrence_id');
    }

    /** @return HasMany<EventResultMetric, $this> */
    public function metrics(): HasMany
    {
        return $this->hasMany(EventResultMetric::class, 'event_result_id');
    }
}
