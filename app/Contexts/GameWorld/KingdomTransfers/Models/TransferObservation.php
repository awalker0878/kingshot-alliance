<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
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
 * @property string|null $target_kingdom_id
 * @property TransferObservationKind $kind
 * @property int|null $numeric_value
 * @property string|null $text_value
 * @property bool|null $boolean_value
 * @property string|null $details
 * @property TransferSourceType $source_type
 * @property string $source_reference
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable|null $valid_until
 * @property string|null $evidence_id
 * @property string $fingerprint
 * @property string|null $recorded_by_player_id
 * @property-read TransferParticipant $participant
 * @property-read Kingdom|null $targetKingdom
 */
final class TransferObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'kind' => TransferObservationKind::class,
            'source_type' => TransferSourceType::class,
            'numeric_value' => 'integer',
            'boolean_value' => 'boolean',
            'observed_at' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<TransferParticipant, $this> */
    public function participant(): BelongsTo
    {
        return $this->belongsTo(TransferParticipant::class, 'transfer_participant_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function targetKingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'target_kingdom_id');
    }
}
