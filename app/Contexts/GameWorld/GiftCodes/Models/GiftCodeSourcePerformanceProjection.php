<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Advisory, rebuildable source-performance projection. It cannot grant authority.
 *
 * @property string $id
 * @property string $gift_code_source_id
 * @property int $observations
 * @property int $unique_codes_discovered
 * @property int $first_discoveries
 * @property int $qualified_observations
 * @property int $confirmed_correct
 * @property int $confirmed_incorrect
 * @property int $conflicting_observations
 * @property int|null $median_discovery_latency_seconds
 * @property int|null $median_confirmation_latency_seconds
 * @property int|null $median_time_to_code_seconds
 * @property int|null $p95_time_to_code_seconds
 * @property float $useful_observation_ratio
 * @property float $quarantine_ratio
 * @property float $duplicate_ratio
 * @property int $latency_sample_count
 * @property CarbonImmutable|null $last_productive_observation_at
 * @property int $revision
 * @property CarbonImmutable $derived_at
 */
final class GiftCodeSourcePerformanceProjection extends Model
{
    use HasUlids;

    protected $table = 'gift_code_source_performance_projections';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'observations',
        'unique_codes_discovered',
        'first_discoveries',
        'qualified_observations',
        'confirmed_correct',
        'confirmed_incorrect',
        'conflicting_observations',
        'median_discovery_latency_seconds',
        'median_confirmation_latency_seconds',
        'median_time_to_code_seconds',
        'p95_time_to_code_seconds',
        'useful_observation_ratio',
        'quarantine_ratio',
        'duplicate_ratio',
        'latency_sample_count',
        'last_productive_observation_at',
        'revision',
        'derived_at',
    ];

    protected function casts(): array
    {
        return [
            'observations' => 'integer',
            'unique_codes_discovered' => 'integer',
            'first_discoveries' => 'integer',
            'qualified_observations' => 'integer',
            'confirmed_correct' => 'integer',
            'confirmed_incorrect' => 'integer',
            'conflicting_observations' => 'integer',
            'median_discovery_latency_seconds' => 'integer',
            'median_confirmation_latency_seconds' => 'integer',
            'median_time_to_code_seconds' => 'integer',
            'p95_time_to_code_seconds' => 'integer',
            'useful_observation_ratio' => 'float',
            'quarantine_ratio' => 'float',
            'duplicate_ratio' => 'float',
            'latency_sample_count' => 'integer',
            'last_productive_observation_at' => 'immutable_datetime',
            'revision' => 'integer',
            'derived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
