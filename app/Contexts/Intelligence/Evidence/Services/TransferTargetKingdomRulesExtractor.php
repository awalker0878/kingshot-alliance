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

        return 'transfer-target-kingdom-rules-v2';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '2.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-target-kingdom-rules/2';
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

            foreach ([
                ['power_cap', ['power cap']],
                ['hero_generation', ['hero generation', 'hero gen']],
                ['truegold_level', ['truegold level', 'truegold']],
                ['character_age_threshold_days', ['character age threshold', 'age threshold', 'days older']],
            ] as [$field, $signals]) {
                $candidate = $this->numericCandidate($line, $signals, $field);
                if ($candidate !== null) {
                    $fields[$field] = $candidate;
                }
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
