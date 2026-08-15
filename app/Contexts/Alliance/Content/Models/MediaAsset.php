<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Content\Enums\MediaLifecycleStatus;
use App\Contexts\Alliance\Content\Enums\MediaScanStatus;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property MediaScanStatus $scan_status
 * @property MediaLifecycleStatus $lifecycle_status
 * @property Carbon|null $scanned_at
 */
final class MediaAsset extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'original_name',
        'disk',
        'path',
        'mime_type',
        'size_bytes',
        'sha256',
        'scan_status',
        'lifecycle_status',
        'uploaded_by_player_id',
        'scanned_at',
    ];

    protected function casts(): array
    {
        return [
            'scan_status' => MediaScanStatus::class,
            'lifecycle_status' => MediaLifecycleStatus::class,
            'scanned_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }
}
