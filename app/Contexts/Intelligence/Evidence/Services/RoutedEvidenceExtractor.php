<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceExtractor;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use RuntimeException;

final readonly class RoutedEvidenceExtractor implements EvidenceExtractor
{
    public function __construct(
        private BearHuntBattleReportExtractor $bearHunt,
        private TransferGovernorStatusExtractor $governorStatus,
        private TransferScorePassesExtractor $scorePasses,
        private TransferInvitationExtractor $invitation,
        private TransferTargetKingdomRulesExtractor $targetRules,
        private TransferOfficialGroupExtractor $officialGroup,
    ) {}

    public function key(EvidenceKind $kind): string
    {
        return $this->extractor($kind)->key($kind);
    }

    public function version(EvidenceKind $kind): string
    {
        return $this->extractor($kind)->version($kind);
    }

    public function schemaVersion(EvidenceKind $kind): string
    {
        return $this->extractor($kind)->schemaVersion($kind);
    }

    public function supports(EvidenceKind $kind): bool
    {
        return $kind !== EvidenceKind::Unknown && $this->extractorOrNull($kind) instanceof EvidenceExtractor;
    }

    public function extract(EvidenceKind $kind, OcrDocument $document): array
    {
        return $this->extractor($kind)->extract($kind, $document);
    }

    private function extractor(EvidenceKind $kind): EvidenceExtractor
    {
        $extractor = $this->extractorOrNull($kind);
        if (! $extractor instanceof EvidenceExtractor) {
            throw new RuntimeException('No extractor supports the classified Evidence kind.');
        }

        return $extractor;
    }

    private function extractorOrNull(EvidenceKind $kind): ?EvidenceExtractor
    {
        return match ($kind) {
            EvidenceKind::BearHuntBattleReport => $this->bearHunt,
            EvidenceKind::TransferGovernorStatus => $this->governorStatus,
            EvidenceKind::TransferScorePasses => $this->scorePasses,
            EvidenceKind::TransferInvitation => $this->invitation,
            EvidenceKind::TransferTargetKingdomRules => $this->targetRules,
            EvidenceKind::TransferOfficialGroup => $this->officialGroup,
            EvidenceKind::Unknown => null,
        };
    }
}
