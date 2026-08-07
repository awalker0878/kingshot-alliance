<?php

declare(strict_types=1);

namespace App\Models;

use App\Domain\Content\Enums\ContentType;
use App\Domain\Content\Enums\ContentVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ContentType $type
 * @property ContentVisibility $visibility
 * @property Carbon|null $created_at
 */
final class ContentRevision extends Model
{
    use HasUlids;

    public $incrementing = false;

    public $timestamps = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'content_item_id',
        'revision_number',
        'category_id',
        'type',
        'visibility',
        'title',
        'summary',
        'body',
        'locale',
        'sort_order',
        'created_by_user_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'visibility' => ContentVisibility::class,
            'created_at' => 'datetime',
        ];
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function item(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class, 'content_item_id');
    }

    /** @return BelongsTo<ContentCategory, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(ContentCategory::class, 'category_id');
    }
}
