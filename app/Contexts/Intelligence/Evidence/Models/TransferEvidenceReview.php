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
 * @property CarbonImmutable $observed_at
 * @property CarbonImmutable|null $valid_until
 * @property CarbonImmutable $reviewed_at
 * @property CarbonImmutable|null $resolved_at
 * @property int|null $governor_power
 * @property int|null $transfer_score
 * @property int|null $transfer_passes_available
 * @property int|null $transfer_passes_required
 * @property int|null $target_power_cap
 */
final class TransferEvidenceReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_transfer_reviews';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'evidence_kind' => EvidenceKind::class,
            'status' => EvidenceReviewStatus::class,
            'revision_number' => 'integer',
            'observed_at' => 'immutable_datetime',
            'valid_until' => 'immutable_datetime',
            'governor_power' => 'integer',
            'transfer_score' => 'integer',
            'transfer_passes_available' => 'integer',
            'transfer_passes_required' => 'integer',
            'target_power_cap' => 'integer',
            'reviewed_at' => 'immutable_datetime',
            'resolved_at' => 'immutable_datetime',
        ];
    }
}
