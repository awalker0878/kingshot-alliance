<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use InvalidArgumentException;

final class TransferScorePassesExtractor extends AbstractTransferEvidenceExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-score-passes-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-score-passes/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TransferScorePasses;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        $fields = [];
        foreach ($document->lines() as $line) {
            $score = $this->numericCandidate($line, ['transfer score'], 'transfer_score');
            if ($score !== null) {
                $fields['transfer_score'] = $score;
                continue;
            }
            $available = $this->numericCandidate($line, ['passes available', 'available passes'], 'transfer_passes_available');
            if ($available !== null) {
                $fields['transfer_passes_available'] = $available;
                continue;
            }
            $required = $this->numericCandidate($line, ['passes required', 'required passes'], 'transfer_passes_required');
            if ($required !== null) {
                $fields['transfer_passes_required'] = $required;
            }
        }

        return array_values($fields);
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Transfer score/pass extractor received an unsupported Evidence kind.');
        }
    }
}
