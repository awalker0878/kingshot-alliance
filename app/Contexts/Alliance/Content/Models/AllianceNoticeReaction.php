<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Content\Enums\NoticeReaction;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** @property NoticeReaction $reaction */
final class AllianceNoticeReaction extends Model
{
    use HasUlids;

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = [
        'alliance_id',
        'content_item_id',
        'player_id',
        'reaction',
    ];

    protected function casts(): array
    {
        return [
            'reaction' => NoticeReaction::class,
        ];
    }

    /** @return BelongsTo<ContentItem, $this> */
    public function contentItem(): BelongsTo
    {
        return $this->belongsTo(ContentItem::class);
    }
}
