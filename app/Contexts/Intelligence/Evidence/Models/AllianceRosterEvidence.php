<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Models;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $alliance_id
 * @property EvidenceLifecycleStatus $lifecycle_status
 * @property string $original_name
 * @property string $disk
 * @property string|null $path
 * @property string $mime_type
 * @property int $size_bytes
 * @property int $width
 * @property int $height
 * @property string $sha256
 * @property string|null $perceptual_hash
 * @property string|null $visual_duplicate_evidence_id
 * @property int|null $visual_duplicate_distance
 * @property string $uploaded_by_player_id
 * @property Carbon $scanned_at
 * @property Carbon|null $binary_deleted_at
 * @property Carbon|null $redacted_at
 * @property Carbon|null $created_at
 */
final class AllianceRosterEvidence extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $table = 'alliance_roster_evidence';

    protected $fillable = [
        'alliance_id', 'lifecycle_status', 'original_name', 'disk', 'path', 'mime_type',
        'size_bytes', 'width', 'height', 'sha256', 'perceptual_hash',
        'visual_duplicate_evidence_id', 'visual_duplicate_distance', 'uploaded_by_player_id',
        'scanned_at', 'binary_deleted_at', 'redacted_at', 'deletion_reason',
    ];

    protected function casts(): array
    {
        return [
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
