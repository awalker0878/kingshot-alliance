<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Kingdoms\Enums\TransferReadinessState;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $alliance_id
 * @property string $transfer_plan_id
 * @property string $transfer_participant_id
 * @property TransferReadinessState|null $from_state
 * @property TransferReadinessState $to_state
 * @property int|null $actor_player_id
 * @property Carbon $created_at
 * @property-read Alliance $alliance
 * @property-read TransferPlan $plan
 * @property-read TransferParticipant $participant
 * @property-read User|null $actor
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

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
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
