<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceExtractionAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'evidence_id', 'classification_attempt_id', 'status', 'extractor_key', 'extractor_version',
        'schema_version', 'input_sha256', 'overall_confidence', 'field_count', 'failure_code',
        'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvidenceAttemptStatus::class,
            'overall_confidence' => 'float',
            'field_count' => 'integer',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
