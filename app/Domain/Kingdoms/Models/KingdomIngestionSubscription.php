<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomIngestionSubscriptionState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $kingdom_id
 * @property string $adapter_key
 * @property string $adapter_version
 * @property KingdomIngestionSubscriptionState $state
 * @property string|null $source_cursor
 * @property Carbon|null $last_succeeded_at
 * @property Carbon|null $last_failed_at
 * @property Carbon|null $blocked_at
 * @property string|null $blocked_reason
 * @property-read Alliance $alliance
 * @property-read Kingdom $kingdom
 * @property-read KingdomIngestionBatch|null $latestBatch
 */
final class KingdomIngestionSubscription extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'kingdom_id',
        'adapter_key',
        'adapter_version',
        'state',
        'source_cursor',
        'last_succeeded_at',
        'last_failed_at',
        'blocked_at',
        'blocked_reason',
    ];

    protected function casts(): array
    {
        return [
            'state' => KingdomIngestionSubscriptionState::class,
            'last_succeeded_at' => 'datetime',
            'last_failed_at' => 'datetime',
            'blocked_at' => 'datetime',
        ];
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

    /** @return HasMany<KingdomIngestionBatch, $this> */
    public function batches(): HasMany
    {
        return $this->hasMany(KingdomIngestionBatch::class, 'subscription_id');
    }

    /** @return HasOne<KingdomIngestionBatch, $this> */
    public function latestBatch(): HasOne
    {
        return $this->hasOne(KingdomIngestionBatch::class, 'subscription_id')->latestOfMany('started_at');
    }

    /** @return HasMany<KingdomIngestionCandidate, $this> */
    public function candidates(): HasMany
    {
        return $this->hasMany(KingdomIngestionCandidate::class, 'subscription_id');
    }
}
