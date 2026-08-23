<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $transfer_participant_id
 * @property TransferReadinessState|null $from_state
 * @property TransferReadinessState $to_state
 * @property string|null $actor_player_id
 * @property Carbon $created_at
 * @property-read TransferPlan $plan
 * @property-read TransferParticipant $participant
 * @property-read Player|null $actor
 */
final class TransferReadinessTransition extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'transfer_participant_id',
        'from_state',
        'to_state',
        'actor_player_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'from_state' => TransferReadinessState::class,
            'to_state' => TransferReadinessState::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<TransferPlan, $this> */
    public function plan(): BelongsTo
    {
        return $this->belongsTo(TransferPlan::class, 'transfer_plan_id');
    }

    /** @return BelongsTo<TransferParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function actor(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'actor_player_id');
    }
}
