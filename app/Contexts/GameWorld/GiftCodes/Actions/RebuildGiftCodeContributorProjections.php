<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeContributorProjection;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

final class RebuildGiftCodeContributorProjections
{
    private const CURSOR_KEY = 'gift-codes:contributor-projections:user-cursor';

    /** @return array{examined:int,updated:int,nextCursor:?int} */
    public function cycle(int $limit = 100): array
    {
        $cursor = Cache::get(self::CURSOR_KEY);
        $afterUserId = is_int($cursor) ? $cursor : (is_numeric($cursor) ? (int) $cursor : null);
        $result = $this->handle($limit, $afterUserId);
        if ($result['nextCursor'] === null) {
            Cache::forget(self::CURSOR_KEY);
        } else {
            Cache::forever(self::CURSOR_KEY, $result['nextCursor']);
        }

        return $result;
    }

    /** @return array{examined:int,updated:int,nextCursor:?int} */
    public function handle(int $limit = 100, ?int $afterUserId = null): array
    {
        if (! (bool) config('game_world.gift_codes.contributor_reputation', false)) {
            return ['examined' => 0, 'updated' => 0, 'nextCursor' => null];
        }
        $limit = max(1, min(500, $limit));
        $users = DB::table('gift_code_provenances')
            ->join('players', 'players.id', '=', 'gift_code_provenances.submitted_by_player_id')
            ->whereNotNull('players.user_id')
            ->when($afterUserId !== null, static fn ($query) => $query->where('players.user_id', '>', $afterUserId))
            ->distinct()
            ->orderBy('players.user_id')
            ->limit($limit + 1)
            ->pluck('players.user_id')
            ->map(static fn ($id): int => (int) $id)
            ->all();
        $hasMore = count($users) > $limit;
        $users = array_slice($users, 0, $limit);
        $updated = 0;

        foreach ($users as $userId) {
            $counts = DB::table('gift_code_provenances')
                ->join('players', 'players.id', '=', 'gift_code_provenances.submitted_by_player_id')
                ->join('gift_codes', 'gift_codes.id', '=', 'gift_code_provenances.gift_code_id')
                ->where('players.user_id', $userId)
                ->selectRaw(
                    'SUM(CASE WHEN gift_code_provenances.verification_state = ? THEN 1 ELSE 0 END) AS accepted_count, '.
                    'SUM(CASE WHEN gift_code_provenances.verification_state = ? AND gift_codes.status = ? THEN 1 ELSE 0 END) AS corroborated_count, '.
                    'SUM(CASE WHEN gift_code_provenances.verification_state = ? THEN 1 ELSE 0 END) AS rejected_count, '.
                    'SUM(CASE WHEN gift_code_provenances.verification_state = ? THEN 1 ELSE 0 END) AS misleading_count',
                    [
                        GiftCodeEvidenceVerificationState::Verified->value,
                        GiftCodeEvidenceVerificationState::Unverified->value,
                        GiftCodeStatus::Valid->value,
                        GiftCodeEvidenceVerificationState::Rejected->value,
                        GiftCodeEvidenceVerificationState::Quarantined->value,
                    ],
                )
                ->first();
            $existing = GiftCodeContributorProjection::query()->find($userId);
            GiftCodeContributorProjection::query()->updateOrCreate(
                ['user_id' => $userId],
                [
                    'accepted_count' => (int) ($counts?->accepted_count ?? 0),
                    'corroborated_count' => (int) ($counts?->corroborated_count ?? 0),
                    'rejected_count' => (int) ($counts?->rejected_count ?? 0),
                    'misleading_count' => (int) ($counts?->misleading_count ?? 0),
                    'revision' => (int) ($existing?->revision ?? 0) + 1,
                    'derived_at' => CarbonImmutable::now('UTC'),
                ],
            );
            $updated++;
        }

        return [
            'examined' => count($users),
            'updated' => $updated,
            'nextCursor' => $hasMore && $users !== [] ? end($users) : null,
        ];
    }
}
