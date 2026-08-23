<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class ContentQuery
{
    /** @return Collection<int, ContentItem> */
    public function publicList(
        string $allianceId,
        ?string $search = null,
        ?string $type = null,
        ?string $category = null,
        ?string $locale = null,
    ): Collection {
        $query = $this->publishedBase($allianceId)
            ->where('visibility', ContentVisibility::Public->value);

        $this->applyFilters($query, $search, $type, $category, $locale);

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(max(1, (int) config('content.public_search_limit', 50)))
            ->get();
    }

    public function publicBySlug(string $allianceId, string $slug): ?ContentItem
    {
        return $this->publishedBase($allianceId)
            ->where('visibility', ContentVisibility::Public->value)
            ->where('slug', $slug)
            ->first();
    }

    /** @return Collection<int, ContentItem> */
    public function memberList(
        string $allianceId,
        ?string $search = null,
        ?string $type = null,
        ?string $category = null,
        ?string $locale = null,
    ): Collection {
        $query = $this->memberPublishedBase($allianceId);
        $this->applyFilters($query, $search, $type, $category, $locale);

        return $query
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(max(1, (int) config('content.member_search_limit', 100)))
            ->get();
    }

    public function memberBySlug(string $allianceId, string $slug): ?ContentItem
    {
        return $this->memberPublishedBase($allianceId)
            ->where('slug', $slug)
            ->first();
    }

    /** @return Collection<int, ContentItem> */
    public function contextualForEventType(string $allianceId, string $eventTypeSlug): Collection
    {
        return $this->memberPublishedBase($allianceId)
            ->whereJsonContains('context_links', [[
                'type' => 'event_type',
                'key' => strtolower(trim($eventTypeSlug)),
            ]])
            ->orderBy('sort_order')
            ->orderByDesc('published_at')
            ->limit(12)
            ->get();
    }

    /** @return Collection<int, ContentItem> */
    public function managerList(string $allianceId): Collection
    {
        return ContentItem::query()
            ->where('alliance_id', $allianceId)
            ->where('slug', '!=', ContentItem::ALLIANCE_RULES_SLUG)
            ->with('category:id,alliance_id,name,slug')
            ->orderByRaw("CASE status WHEN 'draft' THEN 0 WHEN 'scheduled' THEN 1 WHEN 'published' THEN 2 ELSE 3 END")
            ->orderBy('sort_order')
            ->orderByDesc('updated_at')
            ->get();
    }

    /** @return Builder<ContentItem> */
    private function publishedBase(string $allianceId): Builder
    {
        return ContentItem::query()
            ->where('alliance_id', $allianceId)
            ->where('status', ContentStatus::Published->value)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->whereNull('archived_at')
            ->with('category:id,alliance_id,name,slug');
    }

    /** @return Builder<ContentItem> */
    private function memberPublishedBase(string $allianceId): Builder
    {
        return $this->publishedBase($allianceId)
            ->whereIn('visibility', [ContentVisibility::Public->value, ContentVisibility::Members->value]);
    }

    /** @param Builder<ContentItem> $query */
    private function applyFilters(
        Builder $query,
        ?string $search,
        ?string $type,
        ?string $category,
        ?string $locale,
    ): void {
        $search = trim((string) $search);

        if ($search !== '') {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(static function (Builder $query) use ($needle): void {
                $query->whereRaw('LOWER(title) LIKE ?', [$needle])
                    ->orWhereRaw("LOWER(COALESCE(summary, '')) LIKE ?", [$needle])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$needle]);
            });
        }

        if (is_string($type) && $type !== '') {
            $query->where('type', $type);
        }

        if (is_string($category) && $category !== '') {
            $query->whereHas('category', static fn (Builder $categoryQuery) => $categoryQuery->where('slug', $category));
        }

        if (is_string($locale) && $locale !== '') {
            $query->where('locale', strtolower($locale));
        }
    }
}
