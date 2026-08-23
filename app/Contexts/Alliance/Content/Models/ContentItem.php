<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Models;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property ContentType $type
 * @property ContentVisibility $visibility
 * @property ContentStatus $status
 * @property bool $notify_members
 * @property string|null $source_label
 * @property string|null $source_url
 * @property string|null $game_version
 * @property Carbon|null $reviewed_at
 * @property list<array{type:string,key:string}>|null $context_links
 * @property Carbon|null $scheduled_for
 * @property Carbon|null $published_at
 * @property Carbon|null $broadcasted_at
 * @property Carbon|null $archived_at
 */
final class ContentItem extends Model
{
    use HasUlids;

    public const ALLIANCE_RULES_SLUG = 'alliance-rules';

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
        'notify_members',
        'source_label',
        'source_url',
        'game_version',
        'reviewed_at',
        'context_links',
        'scheduled_for',
        'published_at',
        'broadcasted_at',
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
            'notify_members' => 'boolean',
            'reviewed_at' => 'date',
            'context_links' => 'array',
            'scheduled_for' => 'datetime',
            'published_at' => 'datetime',
            'broadcasted_at' => 'datetime',
            'archived_at' => 'datetime',
        ];
    }

    public function provenanceIsComplete(): bool
    {
        if (! $this->type->requiresProvenance()) {
            return true;
        }

        return is_string($this->source_label)
            && trim($this->source_label) !== ''
            && $this->reviewed_at instanceof Carbon;
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
