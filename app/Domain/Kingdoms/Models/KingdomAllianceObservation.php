<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Identity\Models\User;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $tracked_kingdom_alliance_id
 * @property string $kingdom_alliance_id
 * @property int|null $actor_user_id
 * @property string $observed_name
 * @property string|null $observed_tag
 * @property int|null $power
 * @property int|null $member_count
 * @property Carbon $captured_at
 * @property string $source
 * @property string $idempotency_key
 * @property string|null $corrects_observation_id
 * @property Carbon|null $invalidated_at
 * @property int|null $invalidated_by_user_id
 * @property string|null $invalidation_reason
 * @property-read Alliance $alliance
 * @property-read TrackedKingdomAlliance $tracking
 * @property-read KingdomAlliance $kingdomAlliance
 * @property-read User|null $actor
 * @property-read KingdomAllianceObservation|null $correctsObservation
 * @property-read User|null $invalidatedBy
 */
final class KingdomAllianceObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'tracked_kingdom_alliance_id',
        'kingdom_alliance_id',
        'actor_user_id',
        'observed_name',
        'observed_tag',
        'power',
        'member_count',
        'captured_at',
        'source',
        'idempotency_key',
        'corrects_observation_id',
        'invalidated_at',
        'invalidated_by_user_id',
        'invalidation_reason',
    ];

    protected function casts(): array
    {
        return [
            'power' => 'integer',
            'member_count' => 'integer',
            'captured_at' => 'datetime',
            'invalidated_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<TrackedKingdomAlliance, $this> */
    public function tracking(): BelongsTo
    {
        return $this->belongsTo(TrackedKingdomAlliance::class, 'tracked_kingdom_alliance_id');
    }

    /** @return BelongsTo<KingdomAlliance, $this> */
    public function kingdomAlliance(): BelongsTo
    {
        return $this->belongsTo(KingdomAlliance::class);
    }

    /** @return BelongsTo<User, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }

    /** @return BelongsTo<KingdomAllianceObservation, $this> */
    public function correctsObservation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_observation_id');
    }

    /** @return BelongsTo<User, $this> */
    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'invalidated_by_user_id');
    }
}
