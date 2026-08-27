<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

use App\Contexts\GameWorld\Progression\Enums\CalculatorEligibilityStatus;

final readonly class CalculatorEligibility
{
    /**
     * @param list<string> $sourceIds
     * @param array<string,string> $units
     * @param array<string,bool> $gates
     * @param list<string> $blockers
     */
    public function __construct(
        public string $family,
        public CalculatorEligibilityStatus $status,
        public string $reason,
        public string $datasetId,
        public string $datasetVersion,
        public string $datasetChecksum,
        public string $qualificationReportChecksum,
        public string $qualificationStatus,
        public ?string $calculationVersion,
        public array $sourceIds,
        public array $units,
        public array $gates,
        public array $blockers,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'family' => $this->family,
            'status' => $this->status->value,
            'reason' => $this->reason,
            'datasetId' => $this->datasetId,
            'datasetVersion' => $this->datasetVersion,
            'datasetChecksum' => $this->datasetChecksum,
            'qualificationReportChecksum' => $this->qualificationReportChecksum,
            'qualificationStatus' => $this->qualificationStatus,
            'calculationVersion' => $this->calculationVersion,
            'sourceIds' => $this->sourceIds,
            'units' => $this->units,
            'gates' => $this->gates,
            'blockers' => $this->blockers,
        ];
    }
}
