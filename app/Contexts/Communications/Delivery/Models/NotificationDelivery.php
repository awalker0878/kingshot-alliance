<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Enums\DigestCadence;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One concrete route for a logical NotificationMessage.
 *
 * @property string $id
 * @property string $notification_message_id
 * @property DeliveryChannel $channel
 * @property string|null $notification_endpoint_id
 * @property string|null $route_target_label
 * @property DigestCadence $digest_cadence
 * @property CarbonImmutable $due_at
 * @property DeliveryStatus $status
 * @property int $attempt_count
 * @property int $max_attempts
 * @property string $idempotency_key
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $next_attempt_at
 * @property string|null $routing_reason
 * @property string|null $provider_reference
 * @property string|null $last_error
 */
final class NotificationDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'notification_message_id',
        'channel',
        'notification_endpoint_id',
        'route_target_label',
        'digest_cadence',
        'due_at',
        'status',
        'attempt_count',
        'max_attempts',
        'idempotency_key',
        'queued_at',
        'sent_at',
        'failed_at',
        'next_attempt_at',
        'routing_reason',
        'provider_reference',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'digest_cadence' => DigestCadence::class,
            'due_at' => 'immutable_datetime',
            'status' => DeliveryStatus::class,
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
        ];
    }
}
