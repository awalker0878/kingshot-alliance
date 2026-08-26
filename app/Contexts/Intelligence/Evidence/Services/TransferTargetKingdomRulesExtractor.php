<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use InvalidArgumentException;

final class TransferTargetKingdomRulesExtractor extends AbstractTransferEvidenceExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-target-kingdom-rules-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-target-kingdom-rules/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TransferTargetKingdomRules;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        $fields = [];
        foreach ($document->lines() as $line) {
            $kingdom = $this->kingdomNumber($line);
            if ($kingdom !== null && ! isset($fields['target_kingdom_number']) && $line !== []) {
                $fields['target_kingdom_number'] = $this->candidate('target_kingdom_number', 0, array_values($line), (string) $kingdom, 'integer');
            }

            $cap = $this->numericCandidate($line, ['power cap'], 'power_cap');
            if ($cap !== null) {
                $fields['power_cap'] = $cap;
            }

            $text = mb_strtolower($this->lineText($line));
            $classification = match (true) {
                str_contains($text, 'leading kingdom') => 'leading',
                str_contains($text, 'ordinary kingdom') => 'ordinary',
                default => null,
            };
            if ($classification !== null && ! isset($fields['kingdom_classification']) && $line !== []) {
                $fields['kingdom_classification'] = $this->candidate('kingdom_classification', 0, array_values($line), $classification, 'enum');
            }
        }

        return array_values($fields);
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Transfer target Kingdom rules extractor received an unsupported Evidence kind.');
        }
    }
}
