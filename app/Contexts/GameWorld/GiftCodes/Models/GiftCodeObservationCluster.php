<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Rebuildable acquisition-intelligence projection. Never canonical trust.
 *
 * @property string $id
 * @property string $gift_code_id
 * @property string|null $earliest_source_id
 * @property int $observation_count
 * @property int $distinct_source_count
 * @property int $independent_source_count
 * @property int $official_source_count
 * @property CarbonImmutable|null $first_seen_at
 * @property CarbonImmutable|null $earliest_qualified_publication_at
 * @property int|null $time_to_code_seconds
 * @property string $correlation_confidence
 * @property array<string,mixed>|null $correlation_signals
 * @property int $revision
 * @property CarbonImmutable $derived_at
 */
final class GiftCodeObservationCluster extends Model
{
    use HasUlids;

    protected $table = 'gift_code_observation_clusters';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'earliest_source_id',
        'observation_count',
        'distinct_source_count',
        'independent_source_count',
        'official_source_count',
        'first_seen_at',
        'earliest_qualified_publication_at',
        'time_to_code_seconds',
        'correlation_confidence',
        'correlation_signals',
        'revision',
        'derived_at',
    ];

    protected function casts(): array
    {
        return [
            'observation_count' => 'integer',
            'distinct_source_count' => 'integer',
            'independent_source_count' => 'integer',
            'official_source_count' => 'integer',
            'first_seen_at' => 'immutable_datetime',
            'earliest_qualified_publication_at' => 'immutable_datetime',
            'time_to_code_seconds' => 'integer',
            'correlation_signals' => 'array',
            'revision' => 'integer',
            'derived_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function earliestSource(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'earliest_source_id');
    }
}
