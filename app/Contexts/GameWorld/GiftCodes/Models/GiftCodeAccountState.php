<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property string $id
 * @property string $gift_code_id
 * @property int $user_id
 * @property GiftCodeAccountStateStatus $state
 * @property CarbonImmutable|null $snoozed_until
 * @property CarbonImmutable|null $remind_at
 * @property CarbonImmutable|null $last_opened_at
 * @property CarbonImmutable|null $last_action_at
 * @property-read GiftCode $giftCode
 */
final class GiftCodeAccountState extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'user_id',
        'state',
        'snoozed_until',
        'remind_at',
        'last_opened_at',
        'last_action_at',
    ];

    protected function casts(): array
    {
        return [
            'state' => GiftCodeAccountStateStatus::class,
            'snoozed_until' => 'immutable_datetime',
            'remind_at' => 'immutable_datetime',
            'last_opened_at' => 'immutable_datetime',
            'last_action_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }
}
