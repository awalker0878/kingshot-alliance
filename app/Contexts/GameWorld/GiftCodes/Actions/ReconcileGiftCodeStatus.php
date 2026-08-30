<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeTrustResolver;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeTrustDecision;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileGiftCodeStatus
{
    public function __construct(
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
        private GiftCodeTrustResolver $trust,
        private ScheduleGiftCodeNotificationCampaign $notifications,
    ) {}

    public function handle(string $giftCodeId, ?AuditActor $actor = null): GiftCodeStatus
    {
        return DB::transaction(function () use ($giftCodeId, $actor): GiftCodeStatus {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();

            return $this->apply($giftCode, $this->trust->resolve($giftCode), $actor);
        });
    }

    private function apply(GiftCode $giftCode, GiftCodeTrustDecision $decision, ?AuditActor $actor): GiftCodeStatus
    {
        $expiryChanged = ! $this->sameInstant($giftCode->expires_at, $decision->expiresAt)
            || $giftCode->expires_precision !== $decision->expiresPrecision;
        if ($expiryChanged) {
            $giftCode->forceFill([
                'expires_at' => $decision->expiresAt,
                'expires_precision' => $decision->expiresPrecision,
                'expires_revision' => $giftCode->expires_revision + 1,
            ]);
        }

        $materialTrustChanged = $giftCode->status !== $decision->status
            || $giftCode->status_reason_code !== $decision->reasonCode
            || ($giftCode->status_evidence_ids ?? []) !== $decision->evidenceIds;

        if (! $materialTrustChanged) {
            if ($expiryChanged) {
                $previous = $giftCode->status;
                $giftCode->save();
                $this->recordExpiryChange($giftCode, $actor);
                $this->notifications->handle((string) $giftCode->id, $previous, true);
            }

            return $decision->status;
        }

        $previous = $giftCode->status;
        $revision = $giftCode->status_revision + 1;
        $giftCode->forceFill([
            'status' => $decision->status,
            'status_revision' => $revision,
            'status_reason_code' => $decision->reasonCode,
            'status_evidence_ids' => $decision->evidenceIds,
            'status_changed_at' => now(),
            'status_derived_at' => now(),
        ])->save();

        $metadata = [
            'version' => 1,
            'gift_code_id' => (string) $giftCode->id,
            'previous_status' => $previous->value,
            'status' => $decision->status->value,
            'reason_code' => $decision->reasonCode,
            'status_revision' => $revision,
            'evidence_ids' => $decision->evidenceIds,
            'expires_at' => $decision->expiresAt?->toIso8601String(),
            'expires_precision' => $decision->expiresPrecision,
            'expires_revision' => $giftCode->expires_revision,
        ];
        $this->audit->record('game_world.gift_code_status_changed', $actor, $giftCode, null, $metadata);
        $this->outbox->record(
            'gift_code.status_changed',
            null,
            $giftCode,
            $metadata,
            'gift-code:'.$giftCode->id.':status-revision:'.$revision,
            'gift-code:'.$giftCode->id,
        );

        if ($expiryChanged) {
            $this->recordExpiryChange($giftCode, $actor);
        }
        $this->notifications->handle((string) $giftCode->id, $previous, $expiryChanged);

        return $decision->status;
    }

    private function recordExpiryChange(GiftCode $giftCode, ?AuditActor $actor): void
    {
        $metadata = [
            'version' => 1,
            'gift_code_id' => (string) $giftCode->id,
            'expires_at' => $giftCode->expires_at?->toIso8601String(),
            'expires_precision' => $giftCode->expires_precision,
            'expires_revision' => $giftCode->expires_revision,
            'status_revision' => $giftCode->status_revision,
        ];
        $this->audit->record('game_world.gift_code_expiry_changed', $actor, $giftCode, null, $metadata);
        $this->outbox->record(
            'gift_code.expiry_changed',
            null,
            $giftCode,
            $metadata,
            'gift-code:'.$giftCode->id.':expiry-revision:'.$giftCode->expires_revision,
            'gift-code:'.$giftCode->id,
        );
    }

    private function sameInstant(mixed $left, mixed $right): bool
    {
        if ($left === null || $right === null) {
            return $left === null && $right === null;
        }

        return $left->equalTo($right);
    }
}
