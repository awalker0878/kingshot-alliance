<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Models;

use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property Carbon $next_due_at
 * @property Carbon|null $last_queued_at
 */
final class ContributionReportSchedule extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'recipient_player_id',
        'name',
        'cadence',
        'timezone',
        'next_due_at',
        'report_version',
        'is_enabled',
        'last_queued_at',
        'created_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'next_due_at' => 'datetime',
            'is_enabled' => 'boolean',
            'last_queued_at' => 'datetime',
        ];
    }
}
