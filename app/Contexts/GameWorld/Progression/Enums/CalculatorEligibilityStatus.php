<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum CalculatorEligibilityStatus: string
{
    case CalculatorReady = 'calculator_ready';
    case EvidenceIncomplete = 'evidence_incomplete';
    case SourceGap = 'source_gap';
    case EvidenceConflict = 'evidence_conflict';
    case EvidenceReview = 'evidence_review';
    case Disabled = 'disabled';
}
