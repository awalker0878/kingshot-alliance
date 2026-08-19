<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Models;

use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property WebhookDeliveryStatus $status
 * @property array<string, mixed>|null $payload
 * @property Carbon $available_at
 * @property Carbon|null $last_attempt_at
 * @property Carbon|null $delivered_at
 */
final class WebhookDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'webhook_subscription_id',
        'source_message_id',
        'event_type',
        'payload',
        'status',
        'attempts',
        'available_at',
        'last_attempt_at',
        'delivered_at',
        'response_code',
        'response_excerpt',
        'last_error',
        'idempotency_key',
    ];

    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'status' => WebhookDeliveryStatus::class,
            'attempts' => 'integer',
            'available_at' => 'datetime',
            'last_attempt_at' => 'datetime',
            'delivered_at' => 'datetime',
            'response_code' => 'integer',
        ];
    }

    /** @return BelongsTo<WebhookSubscription, $this> */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(WebhookSubscription::class, 'webhook_subscription_id');
    }
}
