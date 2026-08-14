<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon|null $queued_at
 * @property Carbon|null $completed_at
 */
final class ContributionReportRun extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'schedule_id',
        'recipient_player_id',
        'requested_by_player_id',
        'format',
        'status',
        'report_version',
        'filters',
        'row_count',
        'checksum',
        'idempotency_key',
        'queued_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'filters' => 'array',
            'queued_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
