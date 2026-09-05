<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeActionablePairResolver;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSessionProgressor;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class CreateGiftCodeRedemptionSession
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private GiftCodeActionablePairResolver $pairs,
        private GiftCodeRedemptionSessionProgressor $progress,
        private AuditRecorder $audit,
    ) {}

    /**
     * @param list<string> $giftCodeIds
     * @param list<string> $playerIds
     */
    public function handle(
        AuditActor $actor,
        GiftCodeRedemptionSessionMode $mode,
        array $giftCodeIds = [],
        array $playerIds = [],
    ): GiftCodeRedemptionSession {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for a Gift Code session.');
        }
        if (! (bool) config('game_world.gift_codes.redemption_workspace', false)) {
            throw new AuthorizationException('The Gift Code redemption workspace is disabled.');
        }

        $giftCodeIds = array_values(array_unique(array_filter(array_map('trim', $giftCodeIds))));
        $playerIds = array_values(array_unique(array_filter(array_map('trim', $playerIds))));
        $maxCodes = max(1, min(500, (int) config('game_world.gift_codes.max_session_codes', 100)));
        $maxGovernors = max(1, min(100, (int) config('game_world.gift_codes.max_session_governors', 50)));
        $maxItems = max(1, min(2000, (int) config('game_world.gift_codes.max_session_items', 500)));
        if (count($giftCodeIds) > $maxCodes || count($playerIds) > $maxGovernors) {
            throw ValidationException::withMessages(['session' => 'The requested Gift Code session exceeds configured bounds.']);
        }

        $ownedPlayers = $this->players->ownedByUserUpTo($userId, $maxGovernors + 1);
        if (count($ownedPlayers) > $maxGovernors) {
            throw ValidationException::withMessages(['session' => 'This account has more Governors than one Gift Code session may process.']);
        }
        $owned = [];
        foreach ($ownedPlayers as $player) {
            $owned[$player->playerId] = $player;
        }
        if ($playerIds !== []) {
            $selected = [];
            foreach ($playerIds as $playerId) {
                if (! isset($owned[$playerId])) {
                    throw ValidationException::withMessages(['player_ids' => 'A selected Governor is no longer owned by this account.']);
                }
                $selected[$playerId] = $owned[$playerId];
            }
            $owned = $selected;
        }
        if ($owned === []) {
            throw ValidationException::withMessages(['player_ids' => 'At least one owned Governor is required.']);
        }

        $codes = $this->codesForMode($mode, $giftCodeIds, array_keys($owned), $maxCodes);
        if ($codes->count() > $maxCodes) {
            throw ValidationException::withMessages(['gift_code_ids' => 'The requested Gift Code session exceeds the code limit.']);
        }
        if ($codes->isEmpty()) {
            throw ValidationException::withMessages(['gift_code_ids' => 'No Gift Codes match this session mode.']);
        }

        $candidates = [];
        foreach ($codes as $giftCode) {
            foreach ($owned as $player) {
                $decision = $this->pairs->resolve($giftCode, $player);
                if (! $decision->actionable && $mode !== GiftCodeRedemptionSessionMode::Selected) {
                    continue;
                }
                $candidates[] = [$giftCode, $player, $decision];
                if (count($candidates) > $maxItems) {
                    throw ValidationException::withMessages(['session' => 'The requested Gift Code session exceeds the item limit.']);
                }
            }
        }
        if ($candidates === []) {
            throw ValidationException::withMessages(['session' => 'No currently actionable Gift Code/Governor pairs were found.']);
        }

        $now = CarbonImmutable::now('UTC');
        $session = DB::transaction(function () use ($userId, $mode, $candidates, $now): GiftCodeRedemptionSession {
            $session = GiftCodeRedemptionSession::query()->create([
                'user_id' => $userId,
                'mode' => $mode,
                'status' => GiftCodeRedemptionSessionStatus::Active,
                'total_items' => count($candidates),
                'completed_items' => 0,
                'skipped_items' => 0,
                'failed_items' => 0,
                'last_activity_at' => $now,
            ]);

            $sequence = 0;
            foreach ($candidates as [$giftCode, $player, $decision]) {
                ++$sequence;
                GiftCodeRedemptionSessionItem::query()->create([
                    'session_id' => $session->id,
                    'gift_code_id' => $giftCode->id,
                    'player_id' => $player->playerId,
                    'sequence' => $sequence,
                    'state' => $decision->actionable
                        ? GiftCodeRedemptionSessionItemState::Ready
                        : GiftCodeRedemptionSessionItemState::Unavailable,
                    'status_revision_snapshot' => $giftCode->status_revision,
                    'expires_revision_snapshot' => $giftCode->expires_revision,
                    'unavailable_reason' => $decision->actionable ? null : $decision->reason,
                    'completed_at' => $decision->actionable ? null : $now,
                ]);
            }

            return $this->progress->refresh($session)->load('items.giftCode');
        });

        $this->audit->record(
            'game_world.gift_code_redemption_session.created',
            $actor,
            'gift_code_redemption_session',
            (string) $session->id,
            ['mode' => $mode->value, 'items' => $session->total_items],
        );

        return $session;
    }

    /**
     * @param list<string> $giftCodeIds
     * @param list<string> $playerIds
     * @return \Illuminate\Database\Eloquent\Collection<int, GiftCode>
     */
    private function codesForMode(
        GiftCodeRedemptionSessionMode $mode,
        array $giftCodeIds,
        array $playerIds,
        int $limit,
    ) {
        $query = GiftCode::query()->with('factProjections');

        match ($mode) {
            GiftCodeRedemptionSessionMode::Selected => $query->whereIn('id', $giftCodeIds),
            GiftCodeRedemptionSessionMode::AllActionable => $query
                ->where('status', GiftCodeStatus::Valid->value)
                ->where(static fn (Builder $active): Builder => $active->whereNull('expires_at')->orWhere('expires_at', '>', now())),
            GiftCodeRedemptionSessionMode::Expiring => $query
                ->where('status', GiftCodeStatus::Valid->value)
                ->whereBetween('expires_at', [now(), now()->addDay()]),
            GiftCodeRedemptionSessionMode::RetryReady => $query
                ->where('status', GiftCodeStatus::Valid->value)
                ->whereHas('redemptions', static fn (Builder $redemptions): Builder => $redemptions
                    ->whereIn('player_id', $playerIds)
                    ->whereIn('status', [
                        GiftCodeRedemptionStatus::RateLimited->value,
                        GiftCodeRedemptionStatus::TransientFailure->value,
                    ])
                    ->where(static fn (Builder $due): Builder => $due->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now()))),
        };

        return $query
            ->orderByRaw('CASE WHEN expires_at IS NULL THEN 1 ELSE 0 END')
            ->orderBy('expires_at')
            ->orderByDesc('discovered_at')
            ->limit($limit + 1)
            ->get();
    }
}
