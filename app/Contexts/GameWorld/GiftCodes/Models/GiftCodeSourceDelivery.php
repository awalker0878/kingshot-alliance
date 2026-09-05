<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class GiftCodeSourceDelivery extends Model
{
    use HasUlids;

    protected $table = 'gift_code_source_deliveries';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'gift_code_source_id',
        'provider',
        'provider_event_id',
        'provider_item_id',
        'replay_key',
        'payload_sha256',
        'received_at',
        'authenticated_at',
        'signature_valid',
        'processed_at',
        'processing_status',
        'error_code',
        'correlation_id',
    ];

    protected function casts(): array
    {
        return [
            'received_at' => 'immutable_datetime',
            'authenticated_at' => 'immutable_datetime',
            'signature_valid' => 'boolean',
            'processed_at' => 'immutable_datetime',
        ];
    }

    /** @return BelongsTo<GiftCodeSourceRegistry, $this> */
    public function source(): BelongsTo
    {
        return $this->belongsTo(GiftCodeSourceRegistry::class, 'gift_code_source_id');
    }
}
