<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $content_item_id
 * @property string|null $schedule_id
 * @property CarbonImmutable $scheduled_for
 * @property BroadcastRunStatus $status
 * @property int $recipient_count
 * @property int $delivery_count
 * @property string $idempotency_key
 * @property CarbonImmutable|null $queued_at
 * @property CarbonImmutable|null $cancelled_at
 */
final class AnnouncementBroadcastRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'content_item_id',
        'schedule_id',
        'scheduled_for',
        'status',
        'recipient_count',
        'delivery_count',
        'idempotency_key',
        'queued_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => BroadcastRunStatus::class,
            'recipient_count' => 'integer',
            'delivery_count' => 'integer',
            'scheduled_for' => 'immutable_datetime',
            'queued_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
