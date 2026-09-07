<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Models;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $transfer_window_id
 * @property string $kingdom_id
 * @property int|null $ordinary_invites_used
 * @property int|null $transfer_opens_used
 * @property int|null $special_invites_available
 * @property TransferSourceType $source_type
 * @property string $source_reference
 * @property CarbonImmutable $observed_at
 * @property string|null $evidence_id
 * @property bool $is_correction
 * @property string $fingerprint
 * @property string|null $recorded_by_player_id
 * @property-read TransferWindow $window
 * @property-read Kingdom $kingdom
 */
final class TransferKingdomCapacityObservation extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'ordinary_invites_used' => 'integer',
            'transfer_opens_used' => 'integer',
            'special_invites_available' => 'integer',
            'source_type' => TransferSourceType::class,
            'observed_at' => 'immutable_datetime',
            'is_correction' => 'boolean',
        ];
    }

    /** @return BelongsTo<TransferWindow, $this> */
    public function window(): BelongsTo
    {
        return $this->belongsTo(TransferWindow::class, 'transfer_window_id');
    }

    /** @return BelongsTo<Kingdom, $this> */
    public function kingdom(): BelongsTo
    {
        return $this->belongsTo(Kingdom::class, 'kingdom_id');
    }
}
