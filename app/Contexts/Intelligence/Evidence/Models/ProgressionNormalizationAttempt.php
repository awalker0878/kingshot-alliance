<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/** @property EvidenceAttemptStatus $status */
final class ProgressionNormalizationAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_progression_normalization_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EvidenceAttemptStatus::class,
            'normalized_payload' => 'array',
            'warnings' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
