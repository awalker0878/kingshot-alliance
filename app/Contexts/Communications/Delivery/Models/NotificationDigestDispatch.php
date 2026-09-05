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
 * Provider dispatch for a bounded group of recipient-selected digest routes.
 *
 * @property string $id
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property DeliveryChannel $channel
 * @property string|null $notification_endpoint_id
 * @property DigestCadence $cadence
 * @property string $window_key
 * @property DeliveryStatus $status
 * @property CarbonImmutable $due_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $next_attempt_at
 * @property int $attempt_count
 * @property int $max_attempts
 * @property string $idempotency_key
 * @property string|null $last_error
 */
final class NotificationDigestDispatch extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'recipient_user_id',
        'player_id',
        'channel',
        'notification_endpoint_id',
        'cadence',
        'window_key',
        'status',
        'due_at',
        'sent_at',
        'failed_at',
        'next_attempt_at',
        'attempt_count',
        'max_attempts',
        'idempotency_key',
        'last_error',
    ];

    protected function casts(): array
    {
        return [
            'channel' => DeliveryChannel::class,
            'cadence' => DigestCadence::class,
            'status' => DeliveryStatus::class,
            'due_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
        ];
    }
}
