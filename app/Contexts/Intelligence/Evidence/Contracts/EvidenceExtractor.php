<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Contracts;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ExtractedFieldCandidate;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

interface EvidenceExtractor
{
    public function key(EvidenceKind $kind): string;

    public function version(EvidenceKind $kind): string;

    public function schemaVersion(EvidenceKind $kind): string;

    public function supports(EvidenceKind $kind): bool;

    /** @return list<ExtractedFieldCandidate> */
    public function extract(EvidenceKind $kind, OcrDocument $document): array;
}
