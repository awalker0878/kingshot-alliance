<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
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
 * @property string|null $roster_entry_id
 * @property TransferDirection $direction
 * @property string|null $completed_by_player_id
 * @property Carbon $completed_at
 * @property-read TransferPlan $plan
 * @property-read TransferParticipant $participant
 * @property-read Player|null $completedBy
 */
final class TransferCompletion extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'transfer_participant_id',
        'roster_entry_id',
        'direction',
        'completed_by_player_id',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'direction' => TransferDirection::class,
            'completed_at' => 'datetime',
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
    public function completedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'completed_by_player_id');
    }
}
