<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class UpdateGiftCodeAccountState
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(
        AuditActor $actor,
        string $giftCodeId,
        GiftCodeAccountStateStatus $state,
        ?CarbonImmutable $snoozedUntil = null,
        ?CarbonImmutable $remindAt = null,
    ): GiftCodeAccountState {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for personal Gift Code state.');
        }
        GiftCode::query()->findOrFail($giftCodeId);

        $now = CarbonImmutable::now('UTC');
        if ($state === GiftCodeAccountStateStatus::Snoozed) {
            if ($snoozedUntil === null || ! $snoozedUntil->isAfter($now)) {
                throw ValidationException::withMessages(['snoozed_until' => 'A future snooze time is required.']);
            }
        } else {
            $snoozedUntil = null;
        }
        if ($remindAt !== null) {
            $horizon = max(1, (int) config('game_world.gift_codes.reminder_horizon_days', 30));
            if (! $remindAt->isAfter($now) || $remindAt->isAfter($now->addDays($horizon))) {
                throw ValidationException::withMessages(['remind_at' => 'The reminder time is outside the allowed window.']);
            }
        }

        $accountState = DB::transaction(function () use ($giftCodeId, $userId, $state, $snoozedUntil, $remindAt, $now): GiftCodeAccountState {
            $current = GiftCodeAccountState::query()
                ->where('gift_code_id', $giftCodeId)
                ->where('user_id', $userId)
                ->lockForUpdate()
                ->first();
            $current ??= new GiftCodeAccountState([
                'gift_code_id' => $giftCodeId,
                'user_id' => $userId,
            ]);
            $current->state = $state;
            $current->snoozed_until = $snoozedUntil;
            $current->remind_at = $remindAt;
            $current->last_action_at = $now;
            $current->save();

            return $current->refresh();
        });

        $this->audit->record(
            'game_world.gift_codes.account_state_updated',
            $actor,
            'gift_code',
            $giftCodeId,
            [
                'state' => $state->value,
                'snoozed_until' => $snoozedUntil?->toIso8601String(),
                'remind_at' => $remindAt?->toIso8601String(),
            ],
        );

        return $accountState;
    }
}
