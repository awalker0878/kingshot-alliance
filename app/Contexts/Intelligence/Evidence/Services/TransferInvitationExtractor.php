<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use InvalidArgumentException;

final class TransferInvitationExtractor extends AbstractTransferEvidenceExtractor implements EvidenceExtractor
{
    public function key(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-invitation-v1';
    }

    public function version(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return '1.0.0';
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        $this->assertKind($kind);

        return 'transfer-invitation/1';
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind === EvidenceKind::TransferInvitation;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        $this->assertKind($kind);
        $fields = [];
        foreach ($document->lines() as $line) {
            $text = mb_strtolower($this->lineText($line));
            $status = match (true) {
                str_contains($text, 'special invite approved'), str_contains($text, 'special invitation approved') => 'special_approved',
                str_contains($text, 'special invite pending'), str_contains($text, 'special invitation pending') => 'special_pending',
                str_contains($text, 'ordinary invite received'), str_contains($text, 'ordinary invitation received') => 'ordinary_received',
                str_contains($text, 'no invitation'), str_contains($text, 'no invite') => 'none',
                default => null,
            };
            if ($status !== null && ! isset($fields['invitation_status']) && $line !== []) {
                $fields['invitation_status'] = $this->candidate('invitation_status', 0, array_values($line), $status, 'enum');
            }
            $kingdom = $this->kingdomNumber($line);
            if ($kingdom !== null && ! isset($fields['target_kingdom_number']) && $line !== []) {
                $fields['target_kingdom_number'] = $this->candidate('target_kingdom_number', 0, array_values($line), (string) $kingdom, 'integer');
            }
        }

        return array_values($fields);
    }

    private function assertKind(EvidenceKind $kind): void
    {
        if (! $this->supports($kind)) {
            throw new InvalidArgumentException('Transfer invitation extractor received an unsupported Evidence kind.');
        }
    }
}
