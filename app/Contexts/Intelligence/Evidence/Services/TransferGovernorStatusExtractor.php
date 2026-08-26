<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;
use InvalidArgumentException;

final class TransferGovernorStatusExtractor extends AbstractTransferEvidenceExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-governor-status-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-governor-status/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TransferGovernorStatus;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        foreach ($document->lines() as $line) {
            $text = mb_strtolower($this->lineText($line));
            if (str_contains($text, 'power cap')) {
                continue;
            }
            $candidate = $this->numericCandidate($line, ['governor power'], 'governor_power');
            if ($candidate !== null) {
                return [$candidate];
            }
        }

        return [];
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Transfer Governor status extractor received an unsupported Evidence kind.');
        }
    }
}
