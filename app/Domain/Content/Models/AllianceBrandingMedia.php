<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Alliances\Models\Alliance;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

final class AllianceBrandingMedia extends Model
{
    public $incrementing = false;

    protected $primaryKey = null;

    protected $table = 'alliance_branding_media';

    protected $fillable = [
        'alliance_id',
        'slot',
        'media_id',
    ];

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<MediaAsset, $this> */
    public function media(): BelongsTo
    {
        return $this->belongsTo(MediaAsset::class, 'media_id');
    }
}
