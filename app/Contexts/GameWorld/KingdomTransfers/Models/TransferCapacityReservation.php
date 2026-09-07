<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityBucket;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferCapacityReservationState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $transfer_window_id
 * @property string $transfer_plan_id
 * @property string $transfer_participant_id
 * @property string $target_kingdom_id
 * @property TransferCapacityBucket $bucket
 * @property TransferCapacityReservationState $state
 * @property CarbonImmutable|null $reserved_at
 * @property CarbonImmutable|null $released_at
 * @property string|null $notes
 * @property string $created_by_player_id
 */
final class TransferCapacityReservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'bucket' => TransferCapacityBucket::class,
            'state' => TransferCapacityReservationState::class,
            'reserved_at' => 'immutable_datetime',
            'released_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TransferParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id');
    }
}
