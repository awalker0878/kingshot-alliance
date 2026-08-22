<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Models;

use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class BearHuntBattleReport extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'occurrence_id', 'source_evidence_id', 'source_commit_attempt_id', 'idempotency_key',
        'report_fingerprint', 'report_timestamp_text', 'status', 'recorded_by_player_id', 'recorded_at',
        'removed_by_player_id', 'removed_at', 'removal_reason',
    ];

    protected function casts(): array
    {
        return [
            'status' => BearHuntBattleReportStatus::class,
            'recorded_at' => 'datetime',
            'removed_at' => 'datetime',
        ];
    }
}
