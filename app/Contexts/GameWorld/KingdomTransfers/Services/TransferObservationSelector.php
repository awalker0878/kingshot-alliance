<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TransferObservationSelector
{
    /** @param Collection<int, TransferObservation> $observations */
    public function select(Collection $observations, TransferObservationKind $kind, ?string $targetKingdomId, CarbonImmutable $now): TransferObservedValue
    {
        $matching = $observations
            ->filter(static fn (TransferObservation $row): bool => $row->kind === $kind)
            ->filter(static fn (TransferObservation $row): bool => (string) ($row->target_kingdom_id ?? '') === (string) ($targetKingdomId ?? ''))
            ->sortByDesc(static fn (TransferObservation $row): string => $row->observed_at->toIso8601String())
            ->values();

        if ($matching->isEmpty()) return TransferObservedValue::unknown();

        $authoritative = $matching->filter(static fn (TransferObservation $row): bool => $row->source_type->isAuthoritative())->values();
        if ($authoritative->isEmpty()) {
            /** @var TransferObservation $latest */
            $latest = $matching->first();
            return new TransferObservedValue(TransferRequirementState::Unknown, $this->value($latest), $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at), $latest->valid_until === null ? null : CarbonImmutable::instance($latest->valid_until), $latest->details);
        }

        $current = $authoritative->filter(static fn (TransferObservation $row): bool => $row->valid_until !== null && $row->valid_until->gte($now))->values();
        if ($current->isNotEmpty()) {
            $values = $current->map(fn (TransferObservation $row): string => json_encode($this->value($row), JSON_THROW_ON_ERROR))->unique();
            /** @var TransferObservation $latest */
            $latest = $current->first();
            if ($values->count() > 1) {
                return new TransferObservedValue(TransferRequirementState::Conflicting, null, $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at), CarbonImmutable::instance($latest->valid_until), $latest->details);
            }
            return new TransferObservedValue(TransferRequirementState::Met, $this->value($latest), $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at), CarbonImmutable::instance($latest->valid_until), $latest->details);
        }

        /** @var TransferObservation $latest */
        $latest = $authoritative->first();
        $state = $latest->valid_until === null ? TransferRequirementState::Unknown : TransferRequirementState::Stale;
        return new TransferObservedValue($state, $this->value($latest), $latest->source_type, $latest->source_reference, CarbonImmutable::instance($latest->observed_at), $latest->valid_until === null ? null : CarbonImmutable::instance($latest->valid_until), $latest->details);
    }

    private function value(TransferObservation $row): int|string|bool|null
    {
        if ($row->kind->usesNumericValue()) return $row->numeric_value;
        if ($row->kind === TransferObservationKind::InGameRulesVerified) return $row->boolean_value;
        return $row->text_value;
    }
}
