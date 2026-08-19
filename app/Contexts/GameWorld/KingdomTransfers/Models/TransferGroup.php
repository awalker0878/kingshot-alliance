<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferGroupState;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $name
 * @property TransferDirection $direction
 * @property string|null $destination_kingdom_id
 * @property TransferGroupState $state
 * @property string|null $coordinator_player_id
 * @property string|null $manager_notes
 * @property-read TransferPlan $plan
 * @property-read Kingdom|null $destinationKingdom
 * @property-read Player|null $coordinator
 */
final class TransferGroup extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'name',
        'direction',
        'destination_kingdom_id',
        'state',
        'coordinator_player_id',
        'manager_notes',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'state' => TransferGroupState::class,
        ];
    }

    /** @return BelongsTo<TransferPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function destinationKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'destination_kingdom_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function coordinator(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'coordinator_player_id');
    }

    /** @return HasMany<TransferParticipant, $this> */
    public function participants(): HasMany
    {
        return $this->hasMany(TransferParticipant::class, 'transfer_group_id');
    }
}
