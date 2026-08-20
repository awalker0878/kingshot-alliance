<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileGiftCodeStatus
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $giftCodeId, ?AuditActor $actor = null): GiftCodeStatus
    {
        return DB::transaction(function () use ($giftCodeId, $actor): GiftCodeStatus {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();
            $statuses = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCodeId)
                ->get(['status'])
                ->map(static fn (GiftCodeRedemption $redemption): GiftCodeRedemptionStatus => $redemption->status);

            $hasSuccessful = $statuses->contains(
                static fn (GiftCodeRedemptionStatus $status): bool => $status->successful(),
            );
            $hasInvalid = $statuses->contains(GiftCodeRedemptionStatus::InvalidCode);
            $hasExpired = $statuses->contains(GiftCodeRedemptionStatus::Expired);
            $resolved = match (true) {
                $giftCode->expires_at?->isPast() === true => GiftCodeStatus::Expired,
                $hasSuccessful && ($hasInvalid || $hasExpired) => GiftCodeStatus::Disputed,
                $hasExpired => GiftCodeStatus::Expired,
                $hasInvalid => GiftCodeStatus::Invalid,
                $hasSuccessful => GiftCodeStatus::Valid,
                default => GiftCodeStatus::Pending,
            };

            if ($giftCode->status === $resolved) {
                return $resolved;
            }

            $previous = $giftCode->status;
            $giftCode->forceFill([
                'status' => $resolved,
                'status_changed_at' => now(),
            ])->save();
            $metadata = [
                'gift_code_id' => (string) $giftCode->id,
                'previous_status' => $previous->value,
                'status' => $resolved->value,
            ];
            $this->audit->record('game_world.gift_code_status_changed', $actor, $giftCode, null, $metadata);
            $this->outbox->record(
                'gift_code.status_changed',
                null,
                $giftCode,
                $metadata,
                'gift-code:'.$giftCode->id.':status:'.$resolved->value,
                'gift-code:'.$giftCode->id,
            );

            return $resolved;
        });
    }
}
