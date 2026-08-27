<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Services;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceClassifier;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\ValueObjects\ClassificationDecision;
use App\Contexts\Intelligence\Evidence\ValueObjects\OcrDocument;

final class TerritoryMapObservationEvidenceClassifier implements EvidenceClassifier
{
    public function key(): string
    {
        return 'territory-map-observation-classifier';
    }

    public function version(): string
    {
        return '1.0.0';
    }

    public function classify(EvidenceKind $expectedKind, OcrDocument $document): ClassificationDecision
    {
        $text = mb_strtolower($document->text());
        $score = 0.0;
        if (str_contains($text, 'territory') || str_contains($text, 'alliance map') || str_contains($text, 'world map')) {
            $score += 0.32;
        }
        if (preg_match('/\b(?:x|x:)\s*\d{1,4}\s*[, ]+\s*(?:y|y:)\s*\d{1,4}\b/i', $text) === 1
            || preg_match('/\bx\s*[:=]\s*\d{1,4}\b/i', $text) === 1 && preg_match('/\by\s*[:=]\s*\d{1,4}\b/i', $text) === 1) {
            $score += 0.30;
        }
        if (str_contains($text, 'banner') || str_contains($text, 'headquarters') || str_contains($text, 'bear trap')) {
            $score += 0.22;
        }
        if (str_contains($text, 'governor') || str_contains($text, 'city')) {
            $score += 0.12;
        }
        $score = min(0.96, $score);

        if ($score < 0.60) {
            return new ClassificationDecision(EvidenceKind::Unknown, $score, 'The screenshot did not contain enough fixture-backed map/coordinate structure for Territory observation extraction.');
        }

        return new ClassificationDecision(
            EvidenceKind::TerritoryMapObservation,
            $score,
            'Detected a Territory map observation from explicit map, coordinate and supported-object labels.',
        );
    }
}
