<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

final class TransferKingdomConditionSelector
{
    /** @param Collection<int,TransferKingdomConditionObservation> $rows */
    public function value(Collection $rows, string $column): TransferObservedValue
    {
        $authoritative = $rows
            ->filter(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative())
            ->values();

        if ($authoritative->isEmpty()) {
            $latest = $rows->first();

            return $latest instanceof TransferKingdomConditionObservation
                ? new TransferObservedValue(
                    TransferRequirementState::Unknown,
                    $this->scalar($latest->getAttribute($column)),
                    $latest->source_type,
                    $latest->source_reference,
                    CarbonImmutable::instance($latest->observed_at),
                )
                : TransferObservedValue::unknown();
        }

        /** @var TransferKingdomConditionObservation $latest */
        $latest = $authoritative->first();
        $sameTime = $authoritative->filter(
            static fn (TransferKingdomConditionObservation $row): bool => $row->observed_at->equalTo($latest->observed_at),
        );
        $values = $sameTime->map(fn (TransferKingdomConditionObservation $row): int|string|bool|null => $this->scalar($row->getAttribute($column)));
        if ($values->unique(null, true)->count() > 1) {
            return new TransferObservedValue(
                TransferRequirementState::Conflicting,
                null,
                $latest->source_type,
                $latest->source_reference,
                CarbonImmutable::instance($latest->observed_at),
            );
        }

        $value = $this->scalar($latest->getAttribute($column));
        if ($value === null) {
            return new TransferObservedValue(
                TransferRequirementState::Unknown,
                null,
                $latest->source_type,
                $latest->source_reference,
                CarbonImmutable::instance($latest->observed_at),
            );
        }

        return new TransferObservedValue(
            TransferRequirementState::Met,
            $value,
            $latest->source_type,
            $latest->source_reference,
            CarbonImmutable::instance($latest->observed_at),
        );
    }

    /** @param Collection<int,TransferKingdomConditionObservation> $rows */
    public function classification(Collection $rows): TransferKingdomClassification
    {
        $latest = $rows->first(static fn (TransferKingdomConditionObservation $row): bool => $row->source_type->isAuthoritative());

        return $latest instanceof TransferKingdomConditionObservation
            ? ($latest->classification ?? TransferKingdomClassification::Unknown)
            : TransferKingdomClassification::Unknown;
    }

    private function scalar(mixed $value): int|string|bool|null
    {
        return is_int($value) || is_string($value) || is_bool($value) ? $value : null;
    }
}
