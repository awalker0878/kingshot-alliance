<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

final class TransferEvidenceClassifier implements EvidenceClassifier
{
    public function key(): string
    {
        return 'transfer-schema-classifier';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $text = mb_strtolower($document->text());
        $scores = [
            EvidenceKind::TransferGovernorStatus->value => $this->governorStatusScore($text),
            EvidenceKind::TransferScorePasses->value => $this->scorePassesScore($text),
            EvidenceKind::TransferInvitation->value => $this->invitationScore($text),
            EvidenceKind::TransferTargetKingdomRules->value => $this->targetRulesScore($text),
            EvidenceKind::TransferOfficialGroup->value => $this->officialGroupScore($text),
        ];
        arsort($scores, SORT_NUMERIC);
        $keys = array_keys($scores);
        $bestKind = EvidenceKind::from((string) $keys[0]);
        $best = (float) $scores[$bestKind->value];
        $second = isset($keys[1]) ? (float) $scores[(string) $keys[1]] : 0.0;

        if ($best < 0.55) {
            return new ClassificationDecision(EvidenceKind::Unknown, $best, 'The screenshot did not contain enough structure for a supported Transfer evidence schema.');
        }
        if (($best - $second) < 0.10) {
            return new ClassificationDecision(EvidenceKind::Unknown, $best, 'The screenshot matched multiple Transfer evidence schemas too closely for safe extraction.');
        }

        return new ClassificationDecision(
            $bestKind,
            min(1.0, $best),
            sprintf('Detected the %s Transfer screenshot schema from explicit fixture-backed labels.', $bestKind->value),
        );
    }

    private function governorStatusScore(string $text): float
    {
        if (! str_contains($text, 'governor power')) {
            return 0.0;
        }

        return str_contains($text, 'power cap') ? 0.56 : 0.88;
    }

    private function scorePassesScore(string $text): float
    {
        $score = str_contains($text, 'transfer score') ? 0.40 : 0.0;
        if (str_contains($text, 'passes available') || str_contains($text, 'available passes')) {
            $score += 0.27;
        }
        if (str_contains($text, 'passes required') || str_contains($text, 'required passes')) {
            $score += 0.27;
        }

        return min(0.94, $score);
    }

    private function invitationScore(string $text): float
    {
        foreach ([
            'special invite approved',
            'special invitation approved',
            'special invite pending',
            'special invitation pending',
            'ordinary invite received',
            'ordinary invitation received',
            'no invitation',
            'no invite',
        ] as $phrase) {
            if (str_contains($text, $phrase)) {
                return 0.91;
            }
        }

        return 0.0;
    }

    private function targetRulesScore(string $text): float
    {
        $score = str_contains($text, 'power cap') ? 0.55 : 0.0;
        if (str_contains($text, 'leading kingdom') || str_contains($text, 'ordinary kingdom')) {
            $score += 0.23;
        }
        if (preg_match('/\bkingdom\s*#?\s*\d{1,6}\b/i', $text) === 1) {
            $score += 0.12;
        }

        return min(0.90, $score);
    }

    private function officialGroupScore(string $text): float
    {
        if (preg_match('/\btransfer\s+group\b/i', $text) !== 1) {
            return 0.0;
        }
        preg_match_all('/\bkingdom\s*#?\s*\d{1,6}\b/i', $text, $matches);

        return min(0.93, 0.58 + (count($matches[0]) * 0.08));
    }
}
