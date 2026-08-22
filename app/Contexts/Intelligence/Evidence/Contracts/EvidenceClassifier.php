<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

interface EvidenceClassifier
{
    public function key(): string;

    public function version(): string;

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision;
}
