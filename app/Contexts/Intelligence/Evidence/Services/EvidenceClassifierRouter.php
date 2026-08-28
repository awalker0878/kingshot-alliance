<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

final readonly class EvidenceClassifierRouter implements EvidenceClassifier
{
    public function __construct(
        private BearHuntEvidenceClassifier $bearHunt,
        private TransferEvidenceClassifier $transfer,
        private GovernorProgressionEvidenceClassifier $governorProgression,
        private TerritoryMapObservationEvidenceClassifier $territorySpatial,
    ) {}

    public function key(): string
    {
        return 'evidence-family-router';
    }

    public function version(): string
    {
        return '4.0.0';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $decisions = [
            $this->bearHunt->classify($expectedKind, $document),
            $this->transfer->classify($expectedKind, $document),
            $this->governorProgression->classify($expectedKind, $document),
            $this->territorySpatial->classify($expectedKind, $document),
        ];
        $matches = array_values(array_filter(
            $decisions,
            static fn (ClassificationDecision $decision): bool => $decision->kind !== EvidenceKind::Unknown,
        ));
        if ($matches === []) {
            $confidence = max(array_map(static fn (ClassificationDecision $decision): float => $decision->confidence, $decisions));

            return new ClassificationDecision(
                EvidenceKind::Unknown,
                $confidence,
                'The screenshot did not safely match a supported Evidence family.',
            );
        }
        usort($matches, static fn (ClassificationDecision $a, ClassificationDecision $b): int => $b->confidence <=> $a->confidence);
        if (isset($matches[1]) && abs($matches[0]->confidence - $matches[1]->confidence) < 0.10) {
            return new ClassificationDecision(
                EvidenceKind::Unknown,
                $matches[0]->confidence,
                'The screenshot matched multiple Evidence families too closely for safe extraction.',
            );
        }

        return $matches[0];
    }
}
