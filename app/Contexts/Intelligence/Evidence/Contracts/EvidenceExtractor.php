<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

interface EvidenceExtractor
{
    public function key(): string;

    public function version(): string;

    public function schemaVersion(): string;

    public function supports(EvidenceKind $kind): bool;

    /** @return list<ExtractedFieldCandidate> */
    public function extract(OcrDocument $document): array;
}
