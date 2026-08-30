<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use Illuminate\Support\Collection;
use InvalidArgumentException;

final class GiftCodeCatalogQuery
{
    public const VIEW_ACTIVE = 'active';

    public const VIEW_PENDING = 'pending_review';

    public const VIEW_DISPUTED = 'disputed';

    public const VIEW_EXPIRED = 'expired';

    public const VIEW_COMPLETED = 'completed';

    public const VIEW_HISTORY = 'history';

    /** @var list<string> */
    public const VIEWS = [
        self::VIEW_ACTIVE,
        self::VIEW_PENDING,
        self::VIEW_DISPUTED,
        self::VIEW_EXPIRED,
        self::VIEW_COMPLETED,
        self::VIEW_HISTORY,
    ];

    /**
     * @param  non-empty-list<string>  $playerIds
     * @param  array{view?: string, q?: string|null, status?: string|null, source?: string|null, expiry?: string|null, governor_result?: string|null}  $filters
     * @return CursorPaginator<int, GiftCode>
     */
    public function pageForPlayers(
        array $playerIds,
        array $filters = [],
        int $limit = 25,
        ?string $cursor = null,
    ): CursorPaginator {
        $view = trim((string) ($filters['view'] ?? self::VIEW_ACTIVE));
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException('Unsupported Gift Code catalogue view.');
        }

        $limit = max(1, min($limit, 100));
        $query = GiftCode::query()
            ->withCount('provenances')
            ->with(['factProjections', 'redemptions' => static function (Relation $relation) use ($playerIds): void {
                $relation->getQuery()->whereIn('player_id', $playerIds)->orderBy('player_id');
            }]);

        $this->applyView($query, $view, $playerIds);
        $this->applyFilters($query, $filters, $playerIds);
        $this->applyOrdering($query, $view);

        return $query->cursorPaginate(
            perPage: $limit,
            columns: ['gift_codes.*'],
            cursorName: 'cursor',
            cursor: $cursor,
        );
    }

    /**
     * Full evidence/redemption history is intentionally isolated from the index query.
     *
     * @param  non-empty-list<string>  $playerIds
     */
    public function detailForPlayers(string $giftCodeId, array $playerIds): GiftCode
    {
        return GiftCode::query()
            ->whereKey($giftCodeId)
            ->withCount('provenances')
            ->with([
                'provenances.registeredSource',
                'moderationDecisions',
                'factProjections',
                'redemptions' => static function (Relation $relation) use ($playerIds): void {
                    $relation->getQuery()->whereIn('player_id', $playerIds)->orderBy('player_id');
                },
            ])
            ->firstOrFail();
    }

    /** @return Collection<int,GiftCode> */
    public function forPlayer(string $playerId, int $limit = 100): Collection
    {
        return GiftCode::query()
            ->with(['redemptions' => static fn (Relation $relation) => $relation->getQuery()
                ->where('player_id', $playerId)])
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->limit(max(1, min(500, $limit)))
            ->get();
    }

    public function redemptionFor(GiftCode $giftCode, string $playerId): ?GiftCodeRedemption
    {
        $redemption = $giftCode->redemptions->first(
            static fn (GiftCodeRedemption $candidate): bool => $candidate->player_id === $playerId,
        );

        return $redemption instanceof GiftCodeRedemption ? $redemption : null;
    }

    /**
     * @param Builder<GiftCode> $query
     * @param non-empty-list<string> $playerIds
     */
    private function applyView(Builder $query, string $view, array $playerIds): void
    {
        match ($view) {
            self::VIEW_ACTIVE => $query
                ->where('status', GiftCodeStatus::Valid->value)
                ->where(static fn (Builder $active) => $active
                    ->whereNull('expires_at')
                    ->orWhere('expires_at', '>', now())),
            self::VIEW_PENDING => $query->where('status', GiftCodeStatus::Pending->value),
            self::VIEW_DISPUTED => $query->whereIn('status', [
                GiftCodeStatus::Disputed->value,
                GiftCodeStatus::Quarantined->value,
                GiftCodeStatus::Invalid->value,
            ]),
            self::VIEW_EXPIRED => $query->where(static fn (Builder $expired) => $expired
                ->where('status', GiftCodeStatus::Expired->value)
                ->orWhere('expires_at', '<=', now())),
            self::VIEW_COMPLETED => $query->whereHas('redemptions', static fn (Builder $redemptions) => $redemptions
                ->whereIn('player_id', $playerIds)
                ->whereIn('status', [
                    GiftCodeRedemptionStatus::Redeemed->value,
                    GiftCodeRedemptionStatus::AlreadyRedeemed->value,
                ])),
            self::VIEW_HISTORY => null,
            default => throw new InvalidArgumentException('Unsupported Gift Code catalogue view.'),
        };
    }

    /**
     * @param  Builder<GiftCode>  $query
     * @param  array{view?: string, q?: string|null, status?: string|null, source?: string|null, expiry?: string|null, governor_result?: string|null}  $filters
     * @param  non-empty-list<string>  $playerIds
     */
    private function applyFilters(Builder $query, array $filters, array $playerIds): void
    {
        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(static fn (Builder $searchQuery) => $searchQuery
                ->where('normalized_code', 'like', '%'.mb_strtoupper($search).'%')
                ->orWhere('code', 'like', '%'.$search.'%'));
        }

        $status = trim((string) ($filters['status'] ?? ''));
        if ($status !== '') {
            $allowed = array_map(static fn (GiftCodeStatus $case): string => $case->value, GiftCodeStatus::cases());
            if (! in_array($status, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Gift Code status filter.');
            }
            $query->where('status', $status);
        }

        $source = trim((string) ($filters['source'] ?? ''));
        if ($source !== '') {
            $query->whereHas('provenances', static fn (Builder $provenance) => $provenance
                ->where(static fn (Builder $sourceQuery) => $sourceQuery
                    ->where('source_label', 'like', '%'.$source.'%')
                    ->orWhere('source_type', $source)
                    ->orWhereHas('registeredSource', static fn (Builder $registered) => $registered
                        ->where('name', 'like', '%'.$source.'%')
                        ->orWhere('source_key', $source))));
        }

        $expiry = trim((string) ($filters['expiry'] ?? ''));
        match ($expiry) {
            '' => null,
            'none' => $query->whereNull('expires_at'),
            '24h' => $query->whereBetween('expires_at', [now(), now()->addDay()]),
            '7d' => $query->whereBetween('expires_at', [now(), now()->addDays(7)]),
            'expired' => $query->where('expires_at', '<=', now()),
            default => throw new InvalidArgumentException('Unsupported Gift Code expiry filter.'),
        };

        $governorResult = trim((string) ($filters['governor_result'] ?? ''));
        if ($governorResult !== '') {
            $allowed = array_map(
                static fn (GiftCodeRedemptionStatus $case): string => $case->value,
                GiftCodeRedemptionStatus::cases(),
            );
            if (! in_array($governorResult, $allowed, true)) {
                throw new InvalidArgumentException('Unsupported Governor result filter.');
            }
            $query->whereHas('redemptions', static fn (Builder $redemptions) => $redemptions
                ->whereIn('player_id', $playerIds)
                ->where('status', $governorResult));
        }
    }

    /** @param Builder<GiftCode> $query */
    private function applyOrdering(Builder $query, string $view): void
    {
        if ($view === self::VIEW_HISTORY) {
            $query->orderByDesc('discovered_at')->orderByDesc('id');

            return;
        }

        $query
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByRaw("CASE status WHEN 'valid' THEN 0 WHEN 'pending' THEN 1 WHEN 'disputed' THEN 2 WHEN 'quarantined' THEN 3 WHEN 'invalid' THEN 4 WHEN 'expired' THEN 5 ELSE 6 END")
            ->orderByDesc('discovered_at')
            ->orderByDesc('id');
    }
}
