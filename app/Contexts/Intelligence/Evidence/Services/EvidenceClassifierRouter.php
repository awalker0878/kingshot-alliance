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
    ) {}

    public function key(): string
    {
        return 'evidence-family-router';
    }

    public function version(): string
    {
        return '2.0.0';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $bear = $this->bearHunt->classify($expectedKind, $document);
        $transfer = $this->transfer->classify($expectedKind, $document);

        if ($bear->kind === EvidenceKind::Unknown) {
            return $transfer;
        }
        if ($transfer->kind === EvidenceKind::Unknown) {
            return $bear;
        }
        if (abs($bear->confidence - $transfer->confidence) < 0.10) {
            return new ClassificationDecision(
                EvidenceKind::Unknown,
                max($bear->confidence, $transfer->confidence),
                'The screenshot matched multiple Evidence families too closely for safe extraction.',
            );
        }

        return $bear->confidence > $transfer->confidence ? $bear : $transfer;
    }
}
