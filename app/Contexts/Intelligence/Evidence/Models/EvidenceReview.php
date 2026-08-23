<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class EvidenceReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'evidence_id', 'extraction_attempt_id', 'alliance_id', 'occurrence_id', 'revision_number', 'status',
        'report_timestamp_text', 'semantic_fingerprint', 'semantic_duplicate_review_id', 'duplicate_resolution',
        'reviewed_by_player_id', 'resolved_by_player_id', 'reviewed_at', 'resolved_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => EvidenceReviewStatus::class,
            'reviewed_at' => 'datetime',
            'resolved_at' => 'datetime',
        ];
    }
}
