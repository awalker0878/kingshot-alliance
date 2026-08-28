<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property EvidenceCommitStatus $status
 * @property array<string,mixed>|null $destination_receipt
 * @property CarbonImmutable|null $started_at
 * @property CarbonImmutable|null $completed_at
 */
final class SpatialEvidenceCommitAttempt extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_spatial_commit_attempts';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'status' => EvidenceCommitStatus::class,
            'destination_receipt' => 'array',
            'started_at' => 'immutable_datetime',
            'completed_at' => 'immutable_datetime',
        ];
    }
}
