<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

/**
 * @property EvidenceKind $evidence_kind
 * @property EvidenceReviewStatus $status
 * @property CarbonImmutable $captured_at
 * @property CarbonImmutable $reviewed_at
 * @property CarbonImmutable|null $resolved_at
 * @property array<string,mixed> $payload
 */
final class GovernorProgressionEvidenceReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_governor_progression_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_kind' => EvidenceKind::class,
            'status' => EvidenceReviewStatus::class,
            'revision_number' => 'integer',
            'captured_at' => 'immutable_datetime',
            'payload' => 'array',
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
