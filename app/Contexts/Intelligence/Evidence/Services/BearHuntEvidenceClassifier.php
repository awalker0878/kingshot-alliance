<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrToken;

final class BearHuntEvidenceClassifier implements EvidenceClassifier
{
    public function key(): string
    {
        return 'bear-hunt-report';
    }

    public function version(): string
    {
        return '1.1.0';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $text = mb_strtolower($document->text());
        $keywordHits = 0;
        foreach (['bear', 'raging', 'battle record', 'ranking', 'damage points'] as $keyword) {
            if (str_contains($text, $keyword)) {
                $keywordHits++;
            }
        }

        $damageRows = 0;
        foreach ($document->lines() as $line) {
            if ($this->looksLikeDamageLine($line)) {
                $damageRows++;
            }
        }

        $confidence = min(1.0, ($keywordHits * 0.12) + min(0.52, $damageRows * 0.13));
        if ($expectedKind === EvidenceKind::BearHuntBattleReport && $damageRows >= 1 && str_contains($text, 'ranking')) {
            $confidence = max($confidence, 0.62);
        }

        if ($confidence < 0.45) {
            return new ClassificationDecision(EvidenceKind::Unknown, $confidence, 'The screenshot did not contain enough Bear Hunt battle-record structure.');
        }

        return new ClassificationDecision(EvidenceKind::BearHuntBattleReport, $confidence, sprintf('Detected %d damage rows and %d battle-record signals.', $damageRows, $keywordHits));
    }

    /** @param list<OcrToken> $line */
    private function looksLikeDamageLine(array $line): bool
    {
        $text = mb_strtolower(implode(' ', array_map(static fn (OcrToken $token): string => $token->text, $line)));
        if (! str_contains($text, 'damage')) {
            return false;
        }
        foreach ($line as $token) {
            if (preg_match('/^\d[\d,.]*(?:[KMB])?$/i', trim($token->text, ':')) === 1) {
                return true;
            }
        }

        return false;
    }
}
