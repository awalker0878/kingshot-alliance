<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeModerationAction;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeFactDecision;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;

final class GiftCodeFactResolver
{
    /** @return list<GiftCodeFactDecision> */
    public function resolve(GiftCode $giftCode): array
    {
        return [
            $this->resolveType($giftCode, 'reward'),
            $this->resolveType($giftCode, 'applicability'),
        ];
    }

    private function resolveType(GiftCode $giftCode, string $factType): GiftCodeFactDecision
    {
        $moderatorAccepted = $giftCode->moderationDecisions()
            ->where('action', GiftCodeModerationAction::Verify->value)
            ->get(['evidence_ids'])
            ->flatMap(static fn ($decision): array => $decision->evidence_ids ?? [])
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        /** @var Collection<int,GiftCodeProvenance> $evidence */
        $evidence = $giftCode->provenances()
            ->with('registeredSource')
            ->where('assertion', $factType)
            ->get()
            ->filter(function (GiftCodeProvenance $item) use ($moderatorAccepted): bool {
                $accepted = $item->verification_state === GiftCodeEvidenceVerificationState::Verified
                    || in_array((string) $item->id, $moderatorAccepted, true);
                if (! $accepted) {
                    return false;
                }

                if ($item->evidence_classification !== GiftCodeEvidenceClassification::OfficialPublication) {
                    return true;
                }

                return $item->registeredSource !== null
                    && $item->registeredSource->is_active
                    && $item->registeredSource->revoked_at === null;
            })
            ->filter(static fn (GiftCodeProvenance $item): bool => is_array($item->assertion_payload));

        if ($evidence->isEmpty()) {
            return new GiftCodeFactDecision($factType, false, $factType.'_details_unknown', null, []);
        }

        $groups = $evidence->groupBy(static function (GiftCodeProvenance $item): string {
            /** @var array<string,mixed> $payload */
            $payload = $item->assertion_payload;

            return hash('sha256', json_encode(Arr::sortRecursive($payload), JSON_THROW_ON_ERROR));
        });
        $qualified = [];
        $threshold = max(2, (int) config('game_world.gift_codes.independent_evidence_threshold', 2));

        foreach ($groups as $group) {
            /** @var Collection<int,GiftCodeProvenance> $group */
            $official = $group->filter(static fn (GiftCodeProvenance $item): bool =>
                $item->evidence_classification === GiftCodeEvidenceClassification::OfficialPublication);
            $independent = $group->filter(static fn (GiftCodeProvenance $item): bool =>
                $item->evidence_classification === GiftCodeEvidenceClassification::IndependentObservation);
            $independentCount = $independent->map(static fn (GiftCodeProvenance $item): string =>
                $item->registered_source_id ?? $item->fingerprint)->unique()->count();

            if ($official->isEmpty() && $independentCount < $threshold) {
                continue;
            }

            /** @var GiftCodeProvenance $first */
            $first = $group->first();
            $qualified[] = [
                'value' => Arr::sortRecursive($first->assertion_payload ?? []),
                'evidence_ids' => $group->pluck('id')->map(static fn (mixed $id): string => (string) $id)->values()->all(),
            ];
        }

        if ($qualified === []) {
            return new GiftCodeFactDecision($factType, false, 'insufficient_qualified_'.$factType.'_evidence', null, []);
        }
        if (count($qualified) > 1) {
            return new GiftCodeFactDecision(
                $factType,
                false,
                'credible_'.$factType.'_conflict',
                null,
                array_values(array_unique(array_merge(...array_column($qualified, 'evidence_ids')))),
            );
        }

        return new GiftCodeFactDecision(
            $factType,
            true,
            'qualified_'.$factType.'_evidence',
            $qualified[0]['value'],
            $qualified[0]['evidence_ids'],
        );
    }
}
