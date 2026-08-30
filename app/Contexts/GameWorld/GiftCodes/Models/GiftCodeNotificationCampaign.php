<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCodeNotificationCampaign extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_id',
        'notification_type',
        'status_revision',
        'expires_revision',
        'metadata',
        'cursor_user_id',
        'examined_count',
        'delivery_count',
        'created_delivery_count',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status_revision' => 'integer',
            'expires_revision' => 'integer',
            'metadata' => 'array',
            'cursor_user_id' => 'integer',
            'examined_count' => 'integer',
            'delivery_count' => 'integer',
            'created_delivery_count' => 'integer',
            'completed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCode, $this> */
    public function giftCode(): BelongsTo
    {
        return $this->belongsTo(GiftCode::class);
    }
}
