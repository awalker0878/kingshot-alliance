<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeModerationDecision;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeTrustDecision;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class GiftCodeTrustResolver
{
    public function resolve(GiftCode $giftCode): GiftCodeTrustDecision
    {
        /** @var Collection<int, GiftCodeProvenance> $verified */
        $verified = $giftCode->provenances()
            ->with('registeredSource')
            ->where('verification_state', GiftCodeEvidenceVerificationState::Verified->value)
            ->get()
            ->filter(fn (GiftCodeProvenance $evidence): bool => $this->sourceStillAuthoritative($evidence));

        /** @var GiftCodeModerationDecision|null $latestModeration */
        $latestModeration = $giftCode->moderationDecisions()->first();
        if ($latestModeration?->action === GiftCodeModerationAction::Quarantine) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Quarantined,
                'platform_quarantine',
                $this->decisionEvidence($latestModeration),
            );
        }

        $acceptedExpiry = $this->acceptedExpiry($verified);
        if ($acceptedExpiry !== null && $acceptedExpiry['at']->isPast()) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Expired,
                'accepted_expiry_elapsed',
                $acceptedExpiry['evidence_ids'],
                $acceptedExpiry['at'],
                $acceptedExpiry['precision'],
            );
        }

        $positive = $this->qualifiedEvidence($verified, 'available');
        $negative = $this->qualifiedEvidence($verified, 'invalid');

        $redemptions = $giftCode->redemptions()->get(['id', 'player_id', 'status']);
        $successfulRedemptions = $redemptions->filter(
            static fn (GiftCodeRedemption $redemption): bool => $redemption->status->successful(),
        );
        $negativeRedemptions = $redemptions->filter(
            static fn (GiftCodeRedemption $redemption): bool => in_array($redemption->status, [
                GiftCodeRedemptionStatus::InvalidCode,
                GiftCodeRedemptionStatus::Expired,
            ], true),
        );

        $independentThreshold = max(2, (int) config('game_world.gift_codes.independent_evidence_threshold', 2));
        if ($positive === [] && $successfulRedemptions->pluck('player_id')->unique()->count() >= $independentThreshold) {
            $positive = $successfulRedemptions->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
        }
        if ($negative === [] && $negativeRedemptions->pluck('player_id')->unique()->count() >= $independentThreshold) {
            $negative = $negativeRedemptions->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
        }

        if ($positive !== [] && $negative !== []) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Disputed,
                'credible_evidence_conflict',
                array_values(array_unique([...$positive, ...$negative])),
                $acceptedExpiry['at'] ?? null,
                $acceptedExpiry['precision'] ?? null,
            );
        }

        if ($negative !== []) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Invalid,
                'qualified_invalid_evidence',
                $negative,
                $acceptedExpiry['at'] ?? null,
                $acceptedExpiry['precision'] ?? null,
            );
        }

        if ($positive !== []) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Valid,
                'qualified_positive_evidence',
                $positive,
                $acceptedExpiry['at'] ?? null,
                $acceptedExpiry['precision'] ?? null,
            );
        }

        if ($latestModeration?->action === GiftCodeModerationAction::Reject) {
            return new GiftCodeTrustDecision(
                GiftCodeStatus::Invalid,
                'platform_rejected',
                $this->decisionEvidence($latestModeration),
            );
        }

        return new GiftCodeTrustDecision(
            GiftCodeStatus::Pending,
            'awaiting_verified_evidence',
            [],
            $acceptedExpiry['at'] ?? null,
            $acceptedExpiry['precision'] ?? null,
        );
    }

    private function sourceStillAuthoritative(GiftCodeProvenance $evidence): bool
    {
        if ($evidence->evidence_classification !== GiftCodeEvidenceClassification::OfficialPublication) {
            return true;
        }

        $source = $evidence->registeredSource;

        return $source !== null && $source->is_active && $source->revoked_at === null;
    }

    /**
     * @param Collection<int, GiftCodeProvenance> $evidence
     * @return list<string>
     */
    private function qualifiedEvidence(Collection $evidence, string $assertion): array
    {
        $matching = $evidence->filter(
            static fn (GiftCodeProvenance $item): bool => $item->assertion === $assertion,
        );

        $official = $matching->filter(
            static fn (GiftCodeProvenance $item): bool => $item->evidence_classification === GiftCodeEvidenceClassification::OfficialPublication,
        );
        if ($official->isNotEmpty()) {
            return $official->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
        }

        $threshold = max(2, (int) config('game_world.gift_codes.independent_evidence_threshold', 2));
        $independent = $matching->filter(
            static fn (GiftCodeProvenance $item): bool => $item->evidence_classification === GiftCodeEvidenceClassification::IndependentObservation,
        );
        $sourceCount = $independent->map(
            static fn (GiftCodeProvenance $item): string => $item->registered_source_id ?? $item->fingerprint,
        )->unique()->count();

        if ($sourceCount < $threshold) {
            return [];
        }

        return $independent->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all();
    }

    /**
     * @param Collection<int, GiftCodeProvenance> $evidence
     * @return array{at: CarbonImmutable, precision: string|null, evidence_ids: list<string>}|null
     */
    private function acceptedExpiry(Collection $evidence): ?array
    {
        $claims = $evidence
            ->filter(static fn (GiftCodeProvenance $item): bool => $item->claimed_expires_at !== null)
            ->groupBy(static fn (GiftCodeProvenance $item): string => implode('|', [
                $item->claimed_expires_at?->toIso8601String() ?? '',
                $item->expiry_precision ?? '',
            ]));

        foreach ($claims as $group) {
            /** @var Collection<int, GiftCodeProvenance> $group */
            $official = $group->first(
                static fn (GiftCodeProvenance $item): bool => $item->evidence_classification === GiftCodeEvidenceClassification::OfficialPublication,
            );
            if ($official instanceof GiftCodeProvenance && $official->claimed_expires_at !== null) {
                return [
                    'at' => $official->claimed_expires_at,
                    'precision' => $official->expiry_precision,
                    'evidence_ids' => $group->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all(),
                ];
            }

            $threshold = max(2, (int) config('game_world.gift_codes.independent_evidence_threshold', 2));
            $independent = $group->filter(
                static fn (GiftCodeProvenance $item): bool => $item->evidence_classification === GiftCodeEvidenceClassification::IndependentObservation,
            );
            if ($independent->map(
                static fn (GiftCodeProvenance $item): string => $item->registered_source_id ?? $item->fingerprint,
            )->unique()->count() >= $threshold) {
                /** @var GiftCodeProvenance $first */
                $first = $independent->first();

                return [
                    'at' => $first->claimed_expires_at,
                    'precision' => $first->expiry_precision,
                    'evidence_ids' => $independent->pluck('id')->map(static fn ($id): string => (string) $id)->values()->all(),
                ];
            }
        }

        return null;
    }

    /** @return list<string> */
    private function decisionEvidence(GiftCodeModerationDecision $decision): array
    {
        return array_values(array_unique([
            (string) $decision->id,
            ...($decision->evidence_ids ?? []),
        ]));
    }
}
