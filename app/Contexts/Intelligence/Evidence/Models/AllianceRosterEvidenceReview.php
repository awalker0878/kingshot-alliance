<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $evidence_id
 * @property string $alliance_id
 * @property string $schema_version
 * @property int $revision_number
 * @property EvidenceReviewStatus $status
 * @property Carbon $captured_at
 * @property array<string, mixed> $payload
 * @property string $semantic_fingerprint
 * @property string|null $semantic_duplicate_review_id
 * @property string|null $duplicate_resolution
 * @property string $reviewed_by_player_id
 * @property Carbon $reviewed_at
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 */
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
