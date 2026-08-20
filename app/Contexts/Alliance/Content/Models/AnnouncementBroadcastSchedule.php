<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property string $id
 * @property string $alliance_id
 * @property string $content_item_id
 * @property string $created_by_player_id
 * @property string $timezone
 * @property list<int> $weekdays
 * @property string $local_time
 * @property BroadcastScheduleStatus $status
 * @property CarbonImmutable|null $next_run_at
 * @property CarbonImmutable|null $last_run_at
 * @property CarbonImmutable|null $ends_at
 * @property CarbonImmutable|null $cancelled_at
 */
final class AnnouncementBroadcastSchedule extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'content_item_id',
        'created_by_player_id',
        'timezone',
        'weekdays',
        'local_time',
        'status',
        'next_run_at',
        'last_run_at',
        'ends_at',
        'cancelled_at',
    ];

    protected function casts(): array
    {
        return [
            'weekdays' => 'array',
            'status' => BroadcastScheduleStatus::class,
            'next_run_at' => 'immutable_datetime',
            'last_run_at' => 'immutable_datetime',
            'ends_at' => 'immutable_datetime',
            'cancelled_at' => 'immutable_datetime',
        ];
    }
}
