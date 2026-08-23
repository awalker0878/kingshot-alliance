<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferBlockerState;
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
 * @property TransferBlockerState $state
 * @property string $summary
 * @property string|null $details
 * @property string|null $created_by_player_id
 * @property string|null $resolved_by_player_id
 * @property Carbon|null $resolved_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property-read TransferPlan $plan
 * @property-read TransferParticipant $participant
 * @property-read Player|null $createdBy
 * @property-read Player|null $resolvedBy
 */
final class TransferBlocker extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'transfer_plan_id',
        'transfer_participant_id',
        'state',
        'summary',
        'details',
        'created_by_player_id',
        'resolved_by_player_id',
        'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => TransferBlockerState::class,
            'resolved_at' => 'datetime',
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
    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'created_by_player_id');
    }

    /** @return BelongsTo<Player, $this> */
    public function resolvedBy(): BelongsTo
    {
        return $this->belongsTo(Player::class, 'resolved_by_player_id');
    }
}
