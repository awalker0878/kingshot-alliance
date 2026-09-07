<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationAllocationState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationKind;
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
 * @property TransferInvitationKind $kind
 * @property TransferInvitationAllocationState $state
 * @property string|null $notes
 * @property string $created_by_player_id
 */
final class TransferInvitationAllocation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => TransferInvitationKind::class,
            'state' => TransferInvitationAllocationState::class,
        ];
    }

    /** @return BelongsTo<TransferParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id');
    }
}
