<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\ValueObjects;

use App\ReadModels\IntelligenceSignals\Enums\IntelligenceSignalType;

final readonly class IntelligenceSignal
{
    /**
     * @param  list<string>  $sourceRecordIds
     * @param  list<string>  $evidenceIds
     * @param  array<string,mixed>  $metadata
     */
    public function __construct(
        public IntelligenceSignalType $type,
        public string $subjectType,
        public string $subjectId,
        public ?string $metric,
        public string $summary,
        public string $detectedAsOf,
        public string $observedAt,
        public ?string $baselineObservedAt,
        public mixed $currentValue,
        public mixed $previousValue,
        public string|int|float|null $delta,
        public ?float $percentChange,
        public string $state,
        public string $materiality,
        public string $sourceClassification,
        public string $sourceOwner,
        public array $sourceRecordIds,
        public array $evidenceIds,
        public ?string $datasetId,
        public ?string $datasetChecksum,
        public ?string $canonicalUrl,
        public string $fingerprint,
        public string $ruleVersion,
        public array $metadata = [],
    ) {}

    /** @return array<string,mixed> */
    public function toArray(): array
    {
        return [
            'type' => $this->type->value,
            'subjectType' => $this->subjectType,
            'subjectId' => $this->subjectId,
            'metric' => $this->metric,
            'summary' => $this->summary,
            'detectedAsOf' => $this->detectedAsOf,
            'observedAt' => $this->observedAt,
            'baselineObservedAt' => $this->baselineObservedAt,
            'currentValue' => $this->currentValue,
            'previousValue' => $this->previousValue,
            'delta' => $this->delta,
            'percentChange' => $this->percentChange,
            'state' => $this->state,
            'materiality' => $this->materiality,
            'sourceClassification' => $this->sourceClassification,
            'sourceOwner' => $this->sourceOwner,
            'sourceRecordIds' => $this->sourceRecordIds,
            'evidenceIds' => $this->evidenceIds,
            'datasetId' => $this->datasetId,
            'datasetChecksum' => $this->datasetChecksum,
            'canonicalUrl' => $this->canonicalUrl,
            'fingerprint' => $this->fingerprint,
            'ruleVersion' => $this->ruleVersion,
            'metadata' => $this->metadata,
        ];
    }
}
