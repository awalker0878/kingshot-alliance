<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Queries;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Pagination\CursorPaginator;
use InvalidArgumentException;

final class GiftCodeWorkspaceQuery
{
    public const VIEW_NEW = 'new';
    public const VIEW_READY = 'ready';
    public const VIEW_EXPIRING = 'expiring';
    public const VIEW_RETRY_READY = 'retry_ready';
    public const VIEW_IN_PROGRESS = 'in_progress';
    public const VIEW_SNOOZED = 'snoozed';
    public const VIEW_COMPLETED = 'completed';

    /** @var list<string> */
    public const VIEWS = [
        self::VIEW_NEW,
        self::VIEW_READY,
        self::VIEW_EXPIRING,
        self::VIEW_RETRY_READY,
        self::VIEW_IN_PROGRESS,
        self::VIEW_SNOOZED,
        self::VIEW_COMPLETED,
    ];

    /**
     * @param non-empty-list<string> $playerIds
     * @return CursorPaginator<int, GiftCode>
     */
    public function pageForAccount(
        int $userId,
        array $playerIds,
        string $view,
        int $limit = 25,
        ?string $cursor = null,
    ): CursorPaginator {
        if (! in_array($view, self::VIEWS, true)) {
            throw new InvalidArgumentException('Unsupported Gift Code workspace view.');
        }
        $limit = max(1, min(100, $limit));
        $query = $this->queryFor($userId, $playerIds, $view)
            ->withCount('provenances')
            ->with([
                'factProjections',
                'accountStates' => static fn (Relation $relation) => $relation->getQuery()->where('user_id', $userId),
                'redemptions' => static fn (Relation $relation) => $relation->getQuery()->whereIn('player_id', $playerIds)->orderBy('player_id'),
            ])
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->orderByDesc('id');

        return $query->cursorPaginate(
            perPage: $limit,
            columns: ['gift_codes.*'],
            cursorName: 'cursor',
            cursor: $cursor,
        );
    }

    /**
     * @param non-empty-list<string> $playerIds
     * @return array<string,int>
     */
    public function countsForAccount(int $userId, array $playerIds): array
    {
        $counts = [];
        foreach (self::VIEWS as $view) {
            $counts[$view] = $this->queryFor($userId, $playerIds, $view)->count();
        }

        return $counts;
    }

    /**
     * @param non-empty-list<string> $playerIds
     * @return Builder<GiftCode>
     */
    private function queryFor(int $userId, array $playerIds, string $view): Builder
    {
        $success = [GiftCodeRedemptionStatus::Redeemed->value, GiftCodeRedemptionStatus::AlreadyRedeemed->value];
        $active = static fn (Builder $query): Builder => $query
            ->where('status', GiftCodeStatus::Valid->value)
            ->where(static fn (Builder $expiry): Builder => $expiry->whereNull('expires_at')->orWhere('expires_at', '>', now()));
        $notSuppressed = static fn (Builder $query): Builder => $query->whereDoesntHave(
            'accountStates',
            static fn (Builder $state): Builder => $state
                ->where('user_id', $userId)
                ->where(static fn (Builder $suppressed): Builder => $suppressed
                    ->where('state', GiftCodeAccountStateStatus::Dismissed->value)
                    ->orWhere(static fn (Builder $snoozed): Builder => $snoozed
                        ->where('state', GiftCodeAccountStateStatus::Snoozed->value)
                        ->where('snoozed_until', '>', now()))),
        );

        $query = GiftCode::query();
        match ($view) {
            self::VIEW_NEW => $query
                ->where($active)
                ->whereDoesntHave('redemptions', static fn (Builder $redemptions): Builder => $redemptions->whereIn('player_id', $playerIds))
                ->whereDoesntHave('accountStates', static fn (Builder $state): Builder => $state->where('user_id', $userId)),
            self::VIEW_READY => $query
                ->where($active)
                ->where($notSuppressed)
                ->where(static fn (Builder $ready): Builder => $ready
                    ->whereDoesntHave('redemptions', static fn (Builder $redemptions): Builder => $redemptions->whereIn('player_id', $playerIds))
                    ->orWhereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                        ->whereIn('player_id', $playerIds)
                        ->whereNotIn('status', $success))),
            self::VIEW_EXPIRING => $query
                ->where('status', GiftCodeStatus::Valid->value)
                ->whereBetween('expires_at', [now(), now()->addDay()])
                ->where($notSuppressed)
                ->where(static fn (Builder $incomplete): Builder => $incomplete
                    ->whereDoesntHave('redemptions', static fn (Builder $redemptions): Builder => $redemptions->whereIn('player_id', $playerIds))
                    ->orWhereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                        ->whereIn('player_id', $playerIds)
                        ->whereNotIn('status', $success))),
            self::VIEW_RETRY_READY => $query
                ->where($active)
                ->whereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                    ->whereIn('player_id', $playerIds)
                    ->whereIn('status', [GiftCodeRedemptionStatus::RateLimited->value, GiftCodeRedemptionStatus::TransientFailure->value])
                    ->where(static fn (Builder $due): Builder => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))),
            self::VIEW_IN_PROGRESS => $query
                ->where($active)
                ->whereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                    ->whereIn('player_id', $playerIds)
                    ->whereNotIn('status', $success)),
            self::VIEW_SNOOZED => $query->whereHas('accountStates', static fn (Builder $state): Builder => $state
                ->where('user_id', $userId)
                ->where('state', GiftCodeAccountStateStatus::Snoozed->value)
                ->where('snoozed_until', '>', now())),
            self::VIEW_COMPLETED => $query
                ->whereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                    ->whereIn('player_id', $playerIds)
                    ->whereIn('status', $success))
                ->whereDoesntHave('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                    ->whereIn('player_id', $playerIds)
                    ->whereNotIn('status', $success)),
            default => throw new InvalidArgumentException('Unsupported Gift Code workspace view.'),
        };

        return $query;
    }
}
