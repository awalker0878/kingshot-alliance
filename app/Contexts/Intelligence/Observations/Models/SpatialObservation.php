<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Models;

use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

final class SpatialObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'captured_at' => 'datetime',
            'coverage_kind' => SpatialObservationCoverageKind::class,
            'completeness' => SpatialObservationCompleteness::class,
            'coverage_bounds' => 'array',
            'accepted_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /** @return HasMany<SpatialObservedObject, $this> */
    public function objects(): HasMany
    {
        return $this->hasMany(SpatialObservedObject::class, 'spatial_observation_id');
    }
}
