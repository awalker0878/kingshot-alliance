<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

final class GameEvidence extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'game_evidence';

    protected $fillable = [
        'alliance_id',
        'occurrence_id',
        'expected_kind',
        'kind',
        'lifecycle_status',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'width',
        'height',
        'sha256',
        'perceptual_hash',
        'visual_duplicate_evidence_id',
        'visual_duplicate_distance',
        'uploaded_by_player_id',
        'scanned_at',
        'binary_deleted_at',
        'redacted_at',
        'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
            'expected_kind' => EvidenceKind::class,
            'kind' => EvidenceKind::class,
            'lifecycle_status' => EvidenceLifecycleStatus::class,
            'size_bytes' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
            'visual_duplicate_distance' => 'integer',
            'scanned_at' => 'datetime',
            'binary_deleted_at' => 'datetime',
            'redacted_at' => 'datetime',
        ];
    }
}
