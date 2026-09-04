<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Models;

use App\Contexts\Communications\Delivery\Enums\NotificationUrgency;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * One logical recipient-visible notification. Provider delivery state lives on
 * NotificationDelivery so fan-out never duplicates inbox state.
 *
 * @property string $id
 * @property string $notification_type
 * @property int $recipient_user_id
 * @property string|null $player_id
 * @property string|null $subject_type
 * @property string|null $subject_id
 * @property string $title
 * @property string|null $body
 * @property string|null $action_url
 * @property NotificationUrgency $urgency
 * @property CarbonImmutable $available_at
 * @property string $idempotency_key
 * @property CarbonImmutable|null $read_at
 * @property CarbonImmutable|null $archived_at
 * @property array<string,mixed>|null $metadata
 */
final class NotificationMessage extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'notification_type',
        'recipient_user_id',
        'player_id',
        'subject_type',
        'subject_id',
        'title',
        'body',
        'action_url',
        'urgency',
        'available_at',
        'idempotency_key',
        'read_at',
        'archived_at',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'urgency' => NotificationUrgency::class,
            'available_at' => 'immutable_datetime',
            'read_at' => 'immutable_datetime',
            'archived_at' => 'immutable_datetime',
            'metadata' => 'array',
        ];
    }
}
