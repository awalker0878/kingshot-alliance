<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Queries;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentCategory;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Models\ContentRevision;
use App\Contexts\Alliance\Content\Models\MediaAsset;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Shared\Infrastructure\Pagination\PageSlice;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/** Current manager-authorized Content facts; never materializes a full catalogue or history. */
final readonly class ContentManagementQuery
{
    public function __construct(
        private AllianceAuthorization $authorization,
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private ScopedCursorCodec $cursors,
    ) {}

    public function authorize(string $allianceId, string $playerId): void
    {
        if (! $this->authorization->allows($playerId, $allianceId, AlliancePermission::ContentManage)) {
            throw new AuthorizationException;
        }
        $this->kingdoms->requireActive($this->alliances->require($allianceId)->kingdomId);
    }

    /** @return array{page:PageSlice<ContentItem>,total:int} */
    public function catalogue(string $allianceId, string $playerId, ?string $cursor = null, string $search = '', string $status = ''): array
    {
        $this->authorize($allianceId, $playerId);
        $base = $this->items($allianceId)->with('category:id,alliance_id,name,slug');
        if ($status !== '') {
            if (! in_array($status, ['draft', 'scheduled', 'published', 'archived'], true)) {
                throw ValidationException::withMessages(['status' => 'Choose a current content status.']);
            }
            $base->where('status', $status);
        }
        $search = $this->search($base, 'title', $search);

        return $this->page($base, $this->scope($allianceId, $playerId, 'catalogue', $search, $status), $cursor, 20);
    }

    /** @return array{content:int,published:int,scheduled:int,pendingBroadcasts:int} */
    public function totals(string $allianceId, string $playerId): array
    {
        $this->authorize($allianceId, $playerId);
        $row = $this->items($allianceId)->selectRaw('COUNT(*) AS total')
            ->selectRaw("SUM(CASE WHEN status = 'published' THEN 1 ELSE 0 END) AS published")
            ->selectRaw("SUM(CASE WHEN status = 'scheduled' THEN 1 ELSE 0 END) AS scheduled")
            ->selectRaw("SUM(CASE WHEN status = 'published' AND type = 'announcement' AND notify_members = true AND broadcasted_at IS NULL THEN 1 ELSE 0 END) AS pending_broadcasts")
            ->first();

        return ['content' => (int) ($row?->getAttribute('total') ?? 0), 'published' => (int) ($row?->getAttribute('published') ?? 0),
            'scheduled' => (int) ($row?->getAttribute('scheduled') ?? 0), 'pendingBroadcasts' => (int) ($row?->getAttribute('pending_broadcasts') ?? 0)];
    }

    /** @return array{page:PageSlice<ContentCategory>,total:int} */
    public function categories(string $allianceId, string $playerId, ?string $cursor = null, string $search = ''): array
    {
        $this->authorize($allianceId, $playerId);
        $base = ContentCategory::query()->where('alliance_id', $allianceId);
        $search = $this->search($base, 'name', $search);

        return $this->page($base, $this->scope($allianceId, $playerId, 'categories', $search), $cursor, 25);
    }

    /** @return array{page:PageSlice<MediaAsset>,total:int} */
    public function media(string $allianceId, string $playerId, ?string $cursor = null, string $search = ''): array
    {
        $this->authorize($allianceId, $playerId);
        $base = MediaAsset::query()->where('alliance_id', $allianceId);
        $search = $this->search($base, 'original_name', $search);

        return $this->page($base, $this->scope($allianceId, $playerId, 'media', $search), $cursor, 25);
    }

    /** @return array{page:PageSlice<ContentRevision>,total:int} */
    public function revisions(string $allianceId, string $playerId, string $contentId, ?string $cursor = null): array
    {
        $this->requireItem($allianceId, $playerId, $contentId);
        $base = ContentRevision::query()->where('alliance_id', $allianceId)->where('content_item_id', $contentId)
            ->select(['id', 'revision_number', 'title', 'created_at']);

        return $this->page($base, $this->scope($allianceId, $playerId, 'revisions', $contentId), $cursor, 10);
    }

    /** @return array{page:PageSlice<AnnouncementBroadcastRun>,total:int} */
    public function runs(string $allianceId, string $playerId, string $contentId, ?string $cursor = null): array
    {
        $this->requireItem($allianceId, $playerId, $contentId);
        $base = AnnouncementBroadcastRun::query()->where('alliance_id', $allianceId)->where('content_item_id', $contentId);

        return $this->page($base, $this->scope($allianceId, $playerId, 'runs', $contentId), $cursor, 5);
    }

    public function requireItem(string $allianceId, string $playerId, string $contentId): ContentItem
    {
        $this->authorize($allianceId, $playerId);

        return $this->items($allianceId)->whereKey($contentId)->firstOrFail();
    }

    /** @param list<string> $contentIds
     * @return array{schedules:array<string,AnnouncementBroadcastSchedule>,revisions:array<string,int>,runs:array<string,int>}
     */
    public function related(string $allianceId, string $playerId, array $contentIds): array
    {
        $this->authorize($allianceId, $playerId);
        if (count($contentIds) > 20) {
            throw new \InvalidArgumentException('Related management facts require at most one catalogue page.');
        }
        // Re-resolve the actual subject scope, even for internal consumers.
        $subjects = $this->items($allianceId)->whereIn('id', $contentIds)->select('id');
        $schedules = [];
        foreach (AnnouncementBroadcastSchedule::query()->where('alliance_id', $allianceId)->whereIn('content_item_id', clone $subjects)->get() as $schedule) {
            $schedules[(string) $schedule->content_item_id] = $schedule;
        }
        $revisionCounts = ContentRevision::query()->where('alliance_id', $allianceId)->whereIn('content_item_id', clone $subjects)
            ->select('content_item_id')->selectRaw('COUNT(*) AS total')->groupBy('content_item_id')->pluck('total', 'content_item_id');
        $runCounts = AnnouncementBroadcastRun::query()->where('alliance_id', $allianceId)->whereIn('content_item_id', clone $subjects)
            ->select('content_item_id')->selectRaw('COUNT(*) AS total')->groupBy('content_item_id')->pluck('total', 'content_item_id');

        return ['schedules' => $schedules, 'revisions' => $revisionCounts->map(static fn ($n): int => (int) $n)->all(),
            'runs' => $runCounts->map(static fn ($n): int => (int) $n)->all()];
    }

    /** @return array{id:string,name:string}|null */
    public function selectedOption(string $allianceId, string $playerId, string $kind, ?string $id): ?array
    {
        $this->authorize($allianceId, $playerId);
        if ($id === null || $id === '') {
            return null;
        }
        $row = match ($kind) {
            'categories' => ContentCategory::query()->where('alliance_id', $allianceId)->whereKey($id)->first(),
            'media' => MediaAsset::query()->where('alliance_id', $allianceId)->whereKey($id)->first(),
            default => throw new \InvalidArgumentException('Unknown Content option collection.'),
        };

        return $row === null ? null : ['id' => (string) $row->getKey(), 'name' => (string) $row->getAttribute($kind === 'media' ? 'original_name' : 'name')];
    }

    /** @return Builder<ContentItem> */
    private function items(string $allianceId): Builder
    {
        return ContentItem::query()->where('alliance_id', $allianceId)->where('slug', '!=', ContentItem::ALLIANCE_RULES_SLUG);
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @return array{page:PageSlice<T>,total:int}
     */
    private function page(Builder $query, string $scope, ?string $cursor, int $size): array
    {
        $countQuery = clone $query;
        $position = $cursor === null ? null : $this->cursors->decode($cursor, $scope);
        if ($position !== null) {
            if (array_keys($position) !== ['after', 'through'] || ! is_string($position['after']) || ! is_string($position['through'])
                || ! Str::isUlid($position['after']) || ! Str::isUlid($position['through']) || strcmp($position['after'], $position['through']) > 0) {
                throw ValidationException::withMessages(['cursor' => 'The collection cursor does not identify a valid position.']);
            }
            $through = $position['through'];
            $query->where('id', '<=', $through)->where('id', '<', $position['after']);
        } else {
            $through = (clone $query)->max('id');
            if (is_string($through)) {
                $query->where('id', '<=', $through);
            }
        }
        $total = $countQuery->count();
        $rows = $query->orderByDesc('id')->limit($size + 1)->get();
        $more = $rows->count() > $size;
        /** @var list<T> $items */
        $items = array_values($rows->take($size)->all());
        $last = $items === [] ? null : $items[array_key_last($items)];
        $next = $more && $last !== null && is_string($through)
            ? $this->cursors->encode($scope, ['after' => (string) $last->getKey(), 'through' => $through]) : null;

        return ['page' => new PageSlice($items, $next, $size, $cursor === null), 'total' => $total];
    }

    private function scope(string $allianceId, string $playerId, string $kind, string $search = '', string $status = ''): string
    {
        return 'content-management:'.hash('sha256', json_encode([$allianceId, $playerId, $kind, $search, $status], JSON_THROW_ON_ERROR));
    }

    /** @template T of Model
     * @param  Builder<T>  $query
     * @param  'title'|'name'|'original_name'  $column
     */
    private function search(Builder $query, string $column, string $search): string
    {
        $search = mb_strtolower(trim($search));
        if (mb_strlen($search) > 160) {
            throw ValidationException::withMessages(['q' => 'Search is limited to 160 characters.']);
        }
        if ($search !== '') {
            $query->whereRaw(match ($column) {
                'title' => 'LOWER(title) LIKE ?', 'name' => 'LOWER(name) LIKE ?', 'original_name' => 'LOWER(original_name) LIKE ?',
            }, ['%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $search).'%']);
        }

        return $search;
    }
}
