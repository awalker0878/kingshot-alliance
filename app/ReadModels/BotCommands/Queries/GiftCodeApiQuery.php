<?php

declare(strict_types=1);

namespace App\ReadModels\BotCommands\Queries;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\CursorPaginator;

final class GiftCodeApiQuery
{
    /** @return array{items:list<array<string,mixed>>,nextCursor:?string,previousCursor:?string,perPage:int,hasMore:bool} */
    public function page(int $limit = 25, ?string $cursor = null): array
    {
        /** @var CursorPaginator<int, GiftCode> $page */
        $page = GiftCode::query()
            ->where('status', GiftCodeStatus::Valid->value)
            ->where(static fn (Builder $query) => $query
                ->whereNull('expires_at')
                ->orWhere('expires_at', '>', now()))
            ->withCount('provenances')
            ->with('factProjections')
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->orderByDesc('id')
            ->cursorPaginate(
                perPage: max(1, min(100, $limit)),
                columns: ['gift_codes.*'],
                cursorName: 'cursor',
                cursor: $cursor,
            );

        return [
            'items' => array_values(array_map(fn (GiftCode $giftCode): array => $this->item($giftCode), $page->items())),
            'nextCursor' => $page->nextCursor()?->encode(),
            'previousCursor' => $page->previousCursor()?->encode(),
            'perPage' => $page->perPage(),
            'hasMore' => $page->hasMorePages(),
        ];
    }

    /** @return array<string,mixed> */
    private function item(GiftCode $giftCode): array
    {
        $facts = $giftCode->factProjections->keyBy('fact_type');
        $reward = $facts->get('reward');
        $applicability = $facts->get('applicability');

        return [
            'id' => (string) $giftCode->id,
            'code' => $giftCode->code,
            'trust_status' => $giftCode->status->value,
            'reason_code' => $giftCode->status_reason_code,
            'status_revision' => $giftCode->status_revision,
            'source_count' => (int) ($giftCode->getAttribute('provenances_count') ?? 0),
            'discovered_at' => $giftCode->discovered_at->toIso8601String(),
            'expires_at' => $giftCode->expires_at?->toIso8601String(),
            'expires_precision' => $giftCode->expires_precision,
            'expires_revision' => $giftCode->expires_revision,
            'official_handoff_url' => (string) config('game_world.gift_code_redemption_url'),
            'reward' => $reward instanceof GiftCodeFactProjection && $reward->qualified ? $reward->value : null,
            'reward_state' => $reward instanceof GiftCodeFactProjection ? $reward->reason_code : 'reward_details_unknown',
            'applicability' => $applicability instanceof GiftCodeFactProjection && $applicability->qualified
                ? $applicability->value
                : null,
            'applicability_state' => $applicability instanceof GiftCodeFactProjection
                ? $applicability->reason_code
                : 'applicability_details_unknown',
        ];
    }
}
