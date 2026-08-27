<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\ValueObjects;

use App\Contexts\GameWorld\Progression\Enums\ProgressionCalculationStatus;

final readonly class ProgressionCalculationResult
{
    /**
     * @param list<string> $transitionIds
     * @param array<string,array{label:string,quantity:int|float,unit:string}> $resources
     * @param list<string> $sourceIds
     * @param list<string> $assumptions
     */
    public function __construct(
        public ProgressionCalculationStatus $status,
        public string $family,
        public string $currentStateId,
        public string $targetStateId,
        public array $transitionIds,
        public array $resources,
        public string $datasetId,
        public string $datasetVersion,
        public string $datasetChecksum,
        public ?string $calculationVersion,
        public array $sourceIds,
        public array $assumptions,
        public ?string $reason = null,
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'status' => $this->status->value,
            'family' => $this->family,
            'currentStateId' => $this->currentStateId,
            'targetStateId' => $this->targetStateId,
            'transitionIds' => $this->transitionIds,
            'resources' => $this->resources,
            'datasetId' => $this->datasetId,
            'datasetVersion' => $this->datasetVersion,
            'datasetChecksum' => $this->datasetChecksum,
            'calculationVersion' => $this->calculationVersion,
            'sourceIds' => $this->sourceIds,
            'assumptions' => $this->assumptions,
            'reason' => $this->reason,
        ];
    }
}
