<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
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
        private GiftCodeTrustResolver $trustV2,
    ) {}

    public function handle(string $giftCodeId, ?AuditActor $actor = null): GiftCodeStatus
    {
        return DB::transaction(function () use ($giftCodeId, $actor): GiftCodeStatus {
            $giftCode = GiftCode::query()->whereKey($giftCodeId)->lockForUpdate()->firstOrFail();
            $mode = (string) config('game_world.gift_codes.trust_v2', 'shadow');

            if ($mode === 'authoritative') {
                return $this->applyTrustV2($giftCode, $this->trustV2->resolve($giftCode), $actor);
            }

            $legacy = $this->resolveLegacy($giftCode);
            if ($mode === 'shadow') {
                $this->recordShadowComparison($giftCode, $legacy, $this->trustV2->resolve($giftCode), $actor);
            }

            return $this->applyLegacy($giftCode, $legacy, $actor);
        });
    }

    private function resolveLegacy(GiftCode $giftCode): GiftCodeStatus
    {
        $statuses = GiftCodeRedemption::query()
            ->where('gift_code_id', (string) $giftCode->id)
            ->get(['status'])
            ->map(static fn (GiftCodeRedemption $redemption): GiftCodeRedemptionStatus => $redemption->status);

        $hasSuccessful = $statuses->contains(
            static fn (GiftCodeRedemptionStatus $status): bool => $status->successful(),
        );
        $hasInvalid = $statuses->contains(GiftCodeRedemptionStatus::InvalidCode);
        $hasExpired = $statuses->contains(GiftCodeRedemptionStatus::Expired);

        return match (true) {
            $giftCode->expires_at?->isPast() === true => GiftCodeStatus::Expired,
            $hasSuccessful && ($hasInvalid || $hasExpired) => GiftCodeStatus::Disputed,
            $hasExpired => GiftCodeStatus::Expired,
            $hasInvalid => GiftCodeStatus::Invalid,
            $hasSuccessful => GiftCodeStatus::Valid,
            default => GiftCodeStatus::Pending,
        };
    }

    private function applyLegacy(GiftCode $giftCode, GiftCodeStatus $resolved, ?AuditActor $actor): GiftCodeStatus
    {
        if ($giftCode->status === $resolved) {
            return $resolved;
        }

        return $this->transition(
            $giftCode,
            $resolved,
            'legacy_resolver',
            [],
            $actor,
        );
    }

    private function applyTrustV2(GiftCode $giftCode, GiftCodeTrustDecision $decision, ?AuditActor $actor): GiftCodeStatus
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
                $giftCode->save();
                $this->recordExpiryChange($giftCode, $actor);
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
            'gift_code_id' => (string) $giftCode->id,
            'previous_status' => $previous->value,
            'status' => $decision->status->value,
            'reason_code' => $decision->reasonCode,
            'status_revision' => $revision,
            'evidence_ids' => $decision->evidenceIds,
            'expires_at' => $decision->expiresAt?->toIso8601String(),
            'expires_precision' => $decision->expiresPrecision,
            'expires_revision' => $giftCode->expires_revision,
            'resolver' => 'trust_v2',
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

        return $decision->status;
    }

    /** @param list<string> $evidenceIds */
    private function transition(
        GiftCode $giftCode,
        GiftCodeStatus $resolved,
        string $reasonCode,
        array $evidenceIds,
        ?AuditActor $actor,
    ): GiftCodeStatus {
        $previous = $giftCode->status;
        $revision = $giftCode->status_revision + 1;
        $giftCode->forceFill([
            'status' => $resolved,
            'status_revision' => $revision,
            'status_reason_code' => $reasonCode,
            'status_evidence_ids' => $evidenceIds,
            'status_changed_at' => now(),
            'status_derived_at' => now(),
        ])->save();
        $metadata = [
            'gift_code_id' => (string) $giftCode->id,
            'previous_status' => $previous->value,
            'status' => $resolved->value,
            'reason_code' => $reasonCode,
            'status_revision' => $revision,
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

        return $resolved;
    }

    private function recordShadowComparison(
        GiftCode $giftCode,
        GiftCodeStatus $legacy,
        GiftCodeTrustDecision $decision,
        ?AuditActor $actor,
    ): void {
        $giftCode->forceFill([
            'trust_v2_shadow_status' => $decision->status->value,
            'trust_v2_shadow_reason_code' => $decision->reasonCode,
            'trust_v2_compared_at' => now(),
        ])->save();

        if ($legacy === $decision->status) {
            return;
        }

        $this->audit->record('game_world.gift_code_trust_v2_mismatch', $actor, $giftCode, null, [
            'gift_code_id' => (string) $giftCode->id,
            'legacy_status' => $legacy->value,
            'trust_v2_status' => $decision->status->value,
            'trust_v2_reason_code' => $decision->reasonCode,
            'evidence_ids' => $decision->evidenceIds,
        ]);
    }

    private function recordExpiryChange(GiftCode $giftCode, ?AuditActor $actor): void
    {
        $metadata = [
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
