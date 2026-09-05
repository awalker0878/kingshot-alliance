<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeFactProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeActionablePairDecision;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

final class GiftCodeActionablePairResolver
{
    public function resolve(GiftCode $giftCode, PlayerReference $player): GiftCodeActionablePairDecision
    {
        if ($giftCode->status !== GiftCodeStatus::Valid) {
            return GiftCodeActionablePairDecision::unavailable('trust_not_valid');
        }
        if ($giftCode->expires_at?->isPast()) {
            return GiftCodeActionablePairDecision::unavailable('expired');
        }
        if ($player->gamePlayerId === null || trim($player->gamePlayerId) === '') {
            return GiftCodeActionablePairDecision::unavailable('missing_game_player_id');
        }

        $redemption = GiftCodeRedemption::query()
            ->where('gift_code_id', $giftCode->id)
            ->where('player_id', $player->playerId)
            ->first();
        if ($redemption instanceof GiftCodeRedemption) {
            if ($redemption->status->successful()) {
                return GiftCodeActionablePairDecision::unavailable('already_redeemed');
            }
            if ($redemption->status->retryable() && $redemption->next_attempt_at?->isFuture()) {
                return GiftCodeActionablePairDecision::unavailable('retry_not_due', $redemption->next_attempt_at);
            }
            if (in_array($redemption->status, [
                GiftCodeRedemptionStatus::InvalidCode,
                GiftCodeRedemptionStatus::Expired,
                GiftCodeRedemptionStatus::WrongKingdom,
                GiftCodeRedemptionStatus::PermanentFailure,
            ], true)) {
                return GiftCodeActionablePairDecision::unavailable('terminal_governor_failure');
            }
        }

        $applicability = $giftCode->relationLoaded('factProjections')
            ? $giftCode->factProjections->firstWhere('fact_type', 'applicability')
            : GiftCodeFactProjection::query()
                ->where('gift_code_id', $giftCode->id)
                ->where('fact_type', 'applicability')
                ->first();
        if ($applicability instanceof GiftCodeFactProjection && $applicability->qualified) {
            $decision = $this->resolveApplicability($applicability, $player);
            if ($decision !== null) {
                return $decision;
            }
        }

        return GiftCodeActionablePairDecision::actionable();
    }

    private function resolveApplicability(
        GiftCodeFactProjection $projection,
        PlayerReference $player,
    ): ?GiftCodeActionablePairDecision {
        $value = $projection->value;
        if (! is_array($value) || $player->kingdomNumber === null) {
            return null;
        }

        $included = $this->integerList($value['kingdom_numbers'] ?? null);
        if ($included !== [] && ! in_array($player->kingdomNumber, $included, true)) {
            return GiftCodeActionablePairDecision::unavailable('qualified_applicability_excludes_governor');
        }

        $excluded = $this->integerList($value['excluded_kingdom_numbers'] ?? null);
        if (in_array($player->kingdomNumber, $excluded, true)) {
            return GiftCodeActionablePairDecision::unavailable('qualified_applicability_excludes_governor');
        }

        return null;
    }

    /** @return list<int> */
    private function integerList(mixed $value): array
    {
        if (! is_array($value)) {
            return [];
        }

        return array_values(array_unique(array_map('intval', array_filter(
            $value,
            static fn (mixed $item): bool => is_int($item) || (is_string($item) && ctype_digit($item)),
        ))));
    }
}
