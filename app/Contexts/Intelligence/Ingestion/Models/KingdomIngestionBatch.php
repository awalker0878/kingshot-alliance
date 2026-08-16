<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Models;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\Intelligence\Ingestion\Enums\KingdomIngestionBatchState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property string $subscription_id
 * @property string $alliance_id
 * @property string $kingdom_id
 * @property string $adapter_key
 * @property string $adapter_version
 * @property string|null $source_cursor
 * @property string|null $next_source_cursor
 * @property string|null $source_window_id
 * @property KingdomIngestionBatchState $state
 * @property int $records_received
 * @property int $records_staged
 * @property int $records_quarantined
 * @property int $records_rejected
 * @property Carbon $started_at
 * @property Carbon|null $completed_at
 * @property string|null $failure_code
 * @property-read KingdomIngestionSubscription $subscription
 * @property-read Alliance $alliance
 * @property-read Kingdom $kingdom
 */
final class KingdomIngestionBatch extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'subscription_id',
        'alliance_id',
        'kingdom_id',
        'adapter_key',
        'adapter_version',
        'source_cursor',
        'next_source_cursor',
        'source_window_id',
        'state',
        'records_received',
        'records_staged',
        'records_quarantined',
        'records_rejected',
        'started_at',
        'completed_at',
        'failure_code',
    ];

    protected function casts(): array
    {
        return [
            'state' => KingdomIngestionBatchState::class,
            'records_received' => 'integer',
            'records_staged' => 'integer',
            'records_quarantined' => 'integer',
            'records_rejected' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<KingdomIngestionSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(KingdomIngestionSubscription::class, 'subscription_id');
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class);
    }

    /** @return HasMany<KingdomIngestionCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(KingdomIngestionCandidate::class, 'batch_id');
    }
}
