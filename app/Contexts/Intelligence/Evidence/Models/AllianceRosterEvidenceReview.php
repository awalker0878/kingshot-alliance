<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class AllianceRosterEvidenceReview extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'evidence_alliance_roster_reviews';

    protected $fillable = [
        'evidence_id', 'alliance_id', 'schema_version', 'revision_number', 'status',
        'captured_at', 'payload', 'semantic_fingerprint', 'semantic_duplicate_review_id',
        'duplicate_resolution', 'reviewed_by_player_id', 'reviewed_at',
    ];

    protected function casts(): array
    {
        return [
            'revision_number' => 'integer',
            'status' => EvidenceReviewStatus::class,
            'captured_at' => 'datetime',
            'payload' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }
}
