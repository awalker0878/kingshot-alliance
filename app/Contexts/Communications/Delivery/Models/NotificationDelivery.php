<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * Communications-owned delivery state.
 *
 * Business subjects and recipients are represented by scalar boundary identifiers;
 * this aggregate deliberately exposes no Eloquent relationships into source contexts.
 *
 * @property string $id
 * @property string $notification_type
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property string $channel
 * @property string $subject_type
 * @property string $subject_id
 * @property CarbonImmutable|null $due_at
 * @property DeliveryStatus $status
 * @property int $attempt_count
 * @property int $max_attempts
 * @property string $idempotency_key
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $sent_at
 * @property CarbonImmutable|null $failed_at
 * @property CarbonImmutable|null $next_attempt_at
 * @property string|null $last_error
 * @property array<string, mixed>|null $metadata
 */
final class NotificationDelivery extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'notification_type',
        'recipient_user_id',
        'player_id',
        'channel',
        'subject_type',
        'subject_id',
        'due_at',
        'status',
        'attempt_count',
        'max_attempts',
        'idempotency_key',
        'queued_at',
        'sent_at',
        'failed_at',
        'next_attempt_at',
        'last_error',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'due_at' => 'immutable_datetime',
            'status' => DeliveryStatus::class,
            'attempt_count' => 'integer',
            'max_attempts' => 'integer',
            'queued_at' => 'immutable_datetime',
            'sent_at' => 'immutable_datetime',
            'failed_at' => 'immutable_datetime',
            'next_attempt_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
