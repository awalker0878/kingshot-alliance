<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceCommitAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'evidence_id', 'review_id', 'status', 'idempotency_key', 'destination_context',
        'destination_report_id', 'destination_receipt', 'failure_code', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvidenceCommitStatus::class,
            'destination_receipt' => 'array',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
