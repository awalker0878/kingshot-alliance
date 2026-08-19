<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property ContentType $type
 * @property ContentVisibility $visibility
 * @property bool $notify_members
 * @property string|null $source_label
 * @property string|null $source_url
 * @property string|null $game_version
 * @property Carbon|null $reviewed_at
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
        'notify_members',
        'source_label',
        'source_url',
        'game_version',
        'reviewed_at',
        'created_by_player_id',
        'created_at',
    ];

    protected function casts(): array
    {
        return [
            'type' => ContentType::class,
            'visibility' => ContentVisibility::class,
            'notify_members' => 'boolean',
            'reviewed_at' => 'date',
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
