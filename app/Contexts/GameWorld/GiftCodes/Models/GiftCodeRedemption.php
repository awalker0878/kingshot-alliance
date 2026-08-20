<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $gift_code_id
 * @property string $player_id
 * @property string $kingdom_id
 * @property GiftCodeRedemptionStatus $status
 * @property string $provider
 * @property int $attempts
 * @property string|null $last_result_code
 * @property string|null $last_message
 * @property string|null $redemption_url
 * @property CarbonImmutable|null $last_attempt_at
 * @property CarbonImmutable|null $next_attempt_at
 * @property CarbonImmutable|null $redeemed_at
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read GiftCode $giftCode
 */
final class GiftCodeRedemption extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'player_id',
        'kingdom_id',
        'status',
        'provider',
        'attempts',
        'last_result_code',
        'last_message',
        'redemption_url',
        'last_attempt_at',
        'next_attempt_at',
        'redeemed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => GiftCodeRedemptionStatus::class,
            'attempts' => 'integer',
            'last_attempt_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'redeemed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }
}
