<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $tracked_kingdom_alliance_id
 * @property string $kingdom_alliance_id
 * @property int|null $actor_player_id
 * @property string $observed_name
 * @property string|null $observed_tag
 * @property int|null $power
 * @property int|null $member_count
 * @property Carbon $captured_at
 * @property string $source
 * @property string|null $source_subscription_id
 * @property string|null $source_batch_id
 * @property string|null $source_adapter_key
 * @property string|null $source_adapter_version
 * @property string|null $source_record_id
 * @property string|null $source_identity_hash
 * @property string|null $source_payload_hash
 * @property string $idempotency_key
 * @property string|null $corrects_observation_id
 * @property Carbon|null $invalidated_at
 * @property int|null $invalidated_by_player_id
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
        'actor_player_id',
        'observed_name',
        'observed_tag',
        'power',
        'member_count',
        'captured_at',
        'source',
        'source_subscription_id',
        'source_batch_id',
        'source_adapter_key',
        'source_adapter_version',
        'source_record_id',
        'source_identity_hash',
        'source_payload_hash',
        'idempotency_key',
        'corrects_observation_id',
        'invalidated_at',
        'invalidated_by_player_id',
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

    /** @return BelongsTo<Player, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }

    /** @return BelongsTo<KingdomAllianceObservation, $this> */
    public function correctsObservation(): BelongsTo
    {
        return $this->belongsTo(self::class, 'corrects_observation_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function invalidatedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'invalidated_by_player_id');
    }
}
