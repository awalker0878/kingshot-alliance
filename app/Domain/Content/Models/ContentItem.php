<?php

declare(strict_types=1);

namespace App\Domain\Content\Models;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Content\Enums\ContentStatus;
use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ContentType $type
 * @property ContentVisibility $visibility
 * @property ContentStatus $status
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $published_at
 * @property Carbon|null $archived_at
 */
final class ContentItem extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'category_id',
        'type',
        'visibility',
        'status',
        'title',
        'slug',
        'summary',
        'body',
        'locale',
        'sort_order',
        'current_revision_number',
        'scheduled_for',
        'published_at',
        'archived_at',
        'created_by_player_id',
        'updated_by_player_id',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'visibility' => ContentVisibility::class,
            'status' => ContentStatus::class,
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<Alliance, $this> */
    public function alliance(): BelongsTo
    {
        return $this->belongsTo(Alliance::class);
    }

    /** @return BelongsTo<ContentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }

    /** @return HasMany<ContentRevision, $this> */
    public function revisions(): HasMany
    {
        return $this->hasMany(ContentRevision::class)->orderByDesc('revision_number');
    }
}
