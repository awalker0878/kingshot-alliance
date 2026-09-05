<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCodeSourceSubscription extends Model
{
    use HasUlids;

    protected $table = 'gift_code_source_subscriptions';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'provider',
        'transport',
        'provider_subscription_id',
        'topic_or_rule',
        'configured_identity',
        'status',
        'activated_at',
        'expires_at',
        'last_verified_at',
        'last_event_received_at',
        'secret_version',
        'last_error_code',
    ];

    protected function casts(): array
    {
        return [
            'configured_identity' => 'array',
            'activated_at' => 'immutable_datetime',
            'expires_at' => 'immutable_datetime',
            'last_verified_at' => 'immutable_datetime',
            'last_event_received_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
