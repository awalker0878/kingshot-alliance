<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

final class GovernorProgressionEvidenceClassifier implements EvidenceClassifier
{
    public function key(): string
    {
        return 'governor-progression-schema-classifier';
    }

    public function version(): string
    {
        return '1.0.2';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $text = mb_strtolower($document->text());
        $scores = [
            EvidenceKind::GovernorProfile->value => $this->profileScore($text),
            EvidenceKind::GovernorHeroRoster->value => $this->heroRosterScore($text),
            EvidenceKind::GovernorHeroDetail->value => $this->heroDetailScore($text),
            EvidenceKind::GovernorHeroGear->value => $this->heroGearScore($text),
            EvidenceKind::GovernorGear->value => $this->governorGearScore($text),
            EvidenceKind::GovernorCharms->value => $this->charmsScore($text),
        ];
        arsort($scores, SORT_NUMERIC);
        $keys = array_keys($scores);
        $bestKind = EvidenceKind::from((string) $keys[0]);
        $best = (float) $scores[$bestKind->value];
        $second = isset($keys[1]) ? (float) $scores[(string) $keys[1]] : 0.0;

        if ($best < 0.60) {
            return new ClassificationDecision(
                EvidenceKind::Unknown,
                $best,
                'The screenshot did not contain enough fixture-backed structure for a supported Governor Progression schema.',
            );
        }
        if (($best - $second) < 0.10) {
            return new ClassificationDecision(
                EvidenceKind::Unknown,
                $best,
                'The screenshot matched multiple Governor Progression schemas too closely for safe extraction.',
            );
        }

        return new ClassificationDecision(
            $bestKind,
            min(1.0, $best),
            sprintf('Detected the %s Governor Progression screenshot schema from explicit fixture-backed labels.', $bestKind->value),
        );
    }

    private function profileScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'governor profile') || str_contains($text, 'governor info')) {
            $score += 0.62;
        }
        if (str_contains($text, 'governor power') || preg_match('/\bpower\s*[:#]?\s*[\d,]+/i', $text) === 1) {
            $score += 0.20;
        }
        if (str_contains($text, 'town center') || preg_match('/\bkingdom\s*#?\s*\d{1,6}\b/i', $text) === 1) {
            $score += 0.15;
        }

        return min(0.93, $score);
    }

    private function heroRosterScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'hero roster') || str_contains($text, 'my heroes')) {
            $score += 0.62;
        }
        preg_match_all('/\b(?:level|lv\.?)[\s:]*(?:\d{1,2}|80)\b/i', $text, $levels);
        if (count($levels[0]) >= 2) {
            $score += 0.18;
        }
        if (substr_count($text, 'widget') >= 2 || substr_count($text, 'star') >= 2) {
            $score += 0.12;
        }

        return min(0.92, $score);
    }

    private function heroDetailScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'hero detail') || str_contains($text, 'hero details')) {
            $score += 0.64;
        }
        if (str_contains($text, 'widget') || str_contains($text, 'exclusive equipment')) {
            $score += 0.16;
        }
        if (str_contains($text, 'skill') || str_contains($text, 'stars')) {
            $score += 0.12;
        }

        return min(0.92, $score);
    }

    private function heroGearScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'hero gear') || str_contains($text, 'hero equipment')) {
            $score += 0.66;
        }
        if (str_contains($text, 'mastery forge') || str_contains($text, 'mastery')) {
            $score += 0.17;
        }
        if (str_contains($text, 'helmet') || str_contains($text, 'gloves') || str_contains($text, 'boots')) {
            $score += 0.10;
        }

        return min(0.93, $score);
    }

    private function governorGearScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'governor gear') || str_contains($text, 'chief gear')) {
            $score += 0.72;
        }
        if (str_contains($text, 'satin') || str_contains($text, 'gilded thread') || str_contains($text, 'artisan')) {
            $score += 0.12;
        }
        if (str_contains($text, 'gear power') || str_contains($text, 'gear bonus')) {
            $score += 0.08;
        }

        return min(0.94, $score);
    }

    private function charmsScore(string $text): float
    {
        $score = 0.0;
        if (str_contains($text, 'governor charm') || str_contains($text, 'chief charm')) {
            $score += 0.72;
        }
        if (str_contains($text, 'charm guide') || str_contains($text, 'charm design')) {
            $score += 0.12;
        }
        if (preg_match('/\bcharm\s*(?:level|lv\.?)\s*\d+/i', $text) === 1) {
            $score += 0.10;
        }

        return min(0.94, $score);
    }
}
