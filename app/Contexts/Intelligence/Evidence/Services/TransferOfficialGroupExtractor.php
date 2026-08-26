<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use InvalidArgumentException;

final class TransferOfficialGroupExtractor extends AbstractTransferEvidenceExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-official-group-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-official-group/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TransferOfficialGroup;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        $fields = [];
        $kingdoms = [];
        foreach ($document->lines() as $line) {
            $text = $this->lineText($line);
            if (! isset($fields['official_group_identifier'])
                && preg_match('/\btransfer\s+group\s*(?:#|:)?\s*([\p{L}\p{N}][\p{L}\p{N}\-_. ]{0,63})/iu', $text, $match) === 1
                && $line !== []) {
                $identifier = trim($match[1]);
                if ($identifier !== '') {
                    $fields['official_group_identifier'] = $this->candidate('official_group_identifier', 0, array_values($line), $identifier, 'string');
                }
            }

            $kingdom = $this->kingdomNumber($line);
            if ($kingdom !== null && ! isset($kingdoms[$kingdom]) && $line !== []) {
                $kingdoms[$kingdom] = $this->candidate('kingdom_number', count($kingdoms) + 1, array_values($line), (string) $kingdom, 'integer');
            }
        }
        ksort($kingdoms, SORT_NUMERIC);

        return [...array_values($fields), ...array_values($kingdoms)];
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Official Transfer Group extractor received an unsupported Evidence kind.');
        }
    }
}
