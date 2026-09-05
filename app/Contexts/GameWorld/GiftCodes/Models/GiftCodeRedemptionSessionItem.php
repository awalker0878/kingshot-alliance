<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $session_id
 * @property string $gift_code_id
 * @property string $player_id
 * @property int $sequence
 * @property GiftCodeRedemptionSessionItemState $state
 * @property int $status_revision_snapshot
 * @property int $expires_revision_snapshot
 * @property string|null $skip_reason
 * @property string|null $unavailable_reason
 * @property CarbonImmutable|null $completed_at
 * @property-read GiftCodeRedemptionSession $session
 * @property-read GiftCode $giftCode
 */
final class GiftCodeRedemptionSessionItem extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'session_id',
        'gift_code_id',
        'player_id',
        'sequence',
        'state',
        'status_revision_snapshot',
        'expires_revision_snapshot',
        'skip_reason',
        'unavailable_reason',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'sequence' => 'integer',
            'state' => GiftCodeRedemptionSessionItemState::class,
            'status_revision_snapshot' => 'integer',
            'expires_revision_snapshot' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeRedemptionSession, $this> */
    public function session(): BelongsTo
    {
        return $this->belongsTo(GiftCodeRedemptionSession::class, 'session_id');
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }
}
