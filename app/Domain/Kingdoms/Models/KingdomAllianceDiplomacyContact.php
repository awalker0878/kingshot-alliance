<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactChannel;
use App\Domain\Kingdoms\Enums\KingdomAllianceContactState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $tracked_kingdom_alliance_id
 * @property string $kingdom_alliance_id
 * @property string $display_name
 * @property string|null $game_role
 * @property KingdomAllianceContactChannel $channel_type
 * @property string $handle
 * @property KingdomAllianceContactState $state
 * @property Carbon|null $last_verified_at
 * @property string|null $manager_notes
 * @property string|null $created_by_player_id
 * @property string|null $updated_by_player_id
 * @property Carbon|null $deactivated_at
 * @property string|null $deactivated_by_player_id
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read Player|null $createdBy
 * @property-read Player|null $updatedBy
 * @property-read Player|null $deactivatedBy
 */
final class KingdomAllianceDiplomacyContact extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'tracked_kingdom_alliance_id',
        'kingdom_alliance_id',
        'display_name',
        'game_role',
        'channel_type',
        'handle',
        'state',
        'last_verified_at',
        'manager_notes',
        'created_by_player_id',
        'updated_by_player_id',
        'deactivated_at',
        'deactivated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'channel_type' => KingdomAllianceContactChannel::class,
            'state' => KingdomAllianceContactState::class,
            'last_verified_at' => 'datetime',
            'deactivated_at' => 'datetime',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function updatedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'updated_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function deactivatedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'deactivated_by_player_id');
    }
}
