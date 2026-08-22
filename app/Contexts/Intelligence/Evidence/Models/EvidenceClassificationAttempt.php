<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceClassificationAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'evidence_id', 'status', 'classifier_key', 'classifier_version', 'input_sha256',
        'ocr_engine', 'ocr_version', 'ocr_language', 'ocr_payload', 'raw_text',
        'classified_kind', 'confidence', 'reason', 'failure_code', 'started_at', 'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => EvidenceAttemptStatus::class,
            'classified_kind' => EvidenceKind::class,
            'ocr_payload' => 'array',
            'confidence' => 'float',
            'started_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }
}
