<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordGiftCodeRedemptionOutcome
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private ReconcileGiftCodeStatus $reconcile,
    ) {}

    public function handle(
        string $giftCodeId,
        PlayerReference $player,
        string $provider,
        GiftCodeRedemptionOutcome $outcome,
    ): GiftCodeRedemptionReference {
        return DB::transaction(function () use ($giftCodeId, $player, $provider, $outcome): GiftCodeRedemptionReference {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();
            $redemption = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCode->id)
                ->where('player_id', $player->playerId)
                ->lockForUpdate()
                ->first();

            if ($this->requiresPriorHandoff($provider, $outcome) && ! $this->hasOfficialHandoff($redemption)) {
                throw ValidationException::withMessages([
                    'result' => 'Open the official Gift Code Center for this Governor before recording its observed result.',
                ]);
            }

            if (! $redemption instanceof GiftCodeRedemption) {
                $redemption = new GiftCodeRedemption([
                    'gift_code_id' => (string) $giftCode->id,
                    'player_id' => $player->playerId,
                    'kingdom_id' => $player->kingdomId,
                    'attempts' => 0,
                ]);
            } elseif ($redemption->status->successful()
                || ($redemption->status->retryable()
                    && $redemption->next_attempt_at?->isFuture()
                    && $outcome->status->retryable())) {
                return $this->reference($redemption);
            }

            $outcome = $this->boundHandoffToCurrentAvailability($giftCode, $outcome);
            $attempts = $redemption->attempts + 1;
            $boundedOutcome = $this->boundedOutcome($outcome, $attempts);
            $retryAt = $boundedOutcome->retryAt;
            if ($retryAt === null && $boundedOutcome->status->retryable()) {
                $retryAt = CarbonImmutable::now()->addMinutes((int) min(60, 2 ** min($attempts, 6)));
            }

            $redemption->fill([
                'kingdom_id' => $player->kingdomId,
                'status' => $boundedOutcome->status,
                'provider' => $provider,
                'attempts' => $attempts,
                'last_result_code' => $boundedOutcome->resultCode,
                'last_message' => $boundedOutcome->message,
                'redemption_url' => in_array($boundedOutcome->resultCode, ['code_unavailable', 'code_expired'], true)
                    ? null
                    : ($boundedOutcome->redemptionUrl ?? $redemption->redemption_url),
                'last_attempt_at' => now(),
                'next_attempt_at' => $retryAt,
                'redeemed_at' => $boundedOutcome->status->successful() ? now() : $redemption->redeemed_at,
            ]);
            $redemption->save();

            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'gift_code_redemption_id' => (string) $redemption->id,
                'player_id' => $player->playerId,
                'kingdom_id' => $player->kingdomId,
                'status' => $boundedOutcome->status->value,
                'provider' => $provider,
                'attempts' => $attempts,
                'result_code' => $boundedOutcome->resultCode,
                'prior_handoff_required' => $this->requiresPriorHandoff($provider, $boundedOutcome),
            ];
            $this->audit->record('game_world.gift_code_redemption_recorded', $player, $redemption, null, $metadata);
            $this->outbox->record(
                'game_world.gift_code_redemption_recorded',
                null,
                $redemption,
                $metadata,
                'gift-code-redemption:'.$redemption->id.':'.$attempts,
                'player:'.$player->playerId,
            );
            $this->reconcile->handle((string) $giftCode->id, $player);

            return $this->reference($redemption);
        });
    }

    private function requiresPriorHandoff(string $provider, GiftCodeRedemptionOutcome $outcome): bool
    {
        return in_array($provider, ['governor_report', 'governor_observation'], true)
            && $outcome->status !== GiftCodeRedemptionStatus::AwaitingConfirmation;
    }

    private function hasOfficialHandoff(?GiftCodeRedemption $redemption): bool
    {
        return $redemption instanceof GiftCodeRedemption
            && $redemption->redemption_url !== null
            && $redemption->attempts > 0;
    }

    private function boundHandoffToCurrentAvailability(
        GiftCode $giftCode,
        GiftCodeRedemptionOutcome $outcome,
    ): GiftCodeRedemptionOutcome {
        if ($outcome->status !== GiftCodeRedemptionStatus::AwaitingConfirmation) {
            return $outcome;
        }

        if (! $giftCode->status->redeemable()) {
            return new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'code_unavailable',
                'This Gift Code is no longer active.',
            );
        }

        if ($giftCode->expires_at !== null && $giftCode->expires_at->isPast()) {
            return new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'code_expired',
                'This Gift Code has expired.',
            );
        }

        return $outcome;
    }

    private function boundedOutcome(GiftCodeRedemptionOutcome $outcome, int $attempts): GiftCodeRedemptionOutcome
    {
        $maxAttempts = max(1, (int) config('game_world.gift_codes.max_redemption_attempts', 6));
        if (! $outcome->status->retryable() || $attempts < $maxAttempts) {
            return $outcome;
        }

        return new GiftCodeRedemptionOutcome(
            GiftCodeRedemptionStatus::PermanentFailure,
            'retry_limit_reached',
            'The retry limit was reached. Review this Governor before trying again.',
            $outcome->redemptionUrl,
        );
    }

    private function reference(GiftCodeRedemption $redemption): GiftCodeRedemptionReference
    {
        return new GiftCodeRedemptionReference(
            (string) $redemption->id,
            $redemption->status,
            $redemption->attempts,
            $redemption->redemption_url,
            $redemption->next_attempt_at,
            $redemption->redeemed_at,
        );
    }
}
