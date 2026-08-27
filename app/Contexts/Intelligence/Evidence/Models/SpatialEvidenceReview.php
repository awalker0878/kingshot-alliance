<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCompleteness;
use App\Contexts\Intelligence\Observations\Enums\SpatialObservationCoverageKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class SpatialEvidenceReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_spatial_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => EvidenceReviewStatus::class,
            'captured_at' => 'immutable_datetime',
            'coverage_kind' => SpatialObservationCoverageKind::class,
            'completeness' => SpatialObservationCompleteness::class,
            'coverage_bounds' => 'array',
            'payload' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
