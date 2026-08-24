<?php

declare(strict_types=1);

namespace App\ReadModels\AllianceAssistant\ValueObjects;

use App\ReadModels\AllianceAssistant\Enums\EvidenceClassification;
use App\ReadModels\AllianceAssistant\Enums\EvidenceSourceType;

final readonly class AssistantEvidence
{
    /** @param array<string, bool|float|int|string|null> $metadata */
    public function __construct(
        public string $id,
        public EvidenceSourceType $sourceType,
        public string $sourceId,
        public string $title,
        public EvidenceClassification $classification,
        public string $statement,
        public ?string $occurredAt = null,
        public ?string $updatedAt = null,
        public ?string $href = null,
        public array $metadata = [],
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'sourceType' => $this->sourceType->value,
            'sourceId' => $this->sourceId,
            'title' => $this->title,
            'classification' => $this->classification->value,
            'statement' => $this->statement,
            'occurredAt' => $this->occurredAt,
            'updatedAt' => $this->updatedAt,
            'href' => $this->href,
            'metadata' => $this->metadata,
        ];
    }

    /** @return array<string, mixed> */
    public function citation(): array
    {
        return [
            'evidenceId' => $this->id,
            'sourceType' => $this->sourceType->value,
            'sourceId' => $this->sourceId,
            'title' => $this->title,
            'classification' => $this->classification->value,
            'occurredAt' => $this->occurredAt,
            'updatedAt' => $this->updatedAt,
            'href' => $this->href,
        ];
    }
}
