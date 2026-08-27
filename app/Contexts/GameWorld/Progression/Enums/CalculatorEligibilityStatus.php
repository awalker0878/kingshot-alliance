<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Progression\Enums;

enum CalculatorEligibilityStatus: string
{
    case CalculatorReady = 'calculator_ready';
    case QualifiedPendingImplementation = 'qualified_pending_implementation';
    case EvidenceReview = 'evidence_review';
    case EvidenceIncomplete = 'evidence_incomplete';
    case SourceGap = 'source_gap';
    case EvidenceConflict = 'evidence_conflict';
    case Unsupported = 'unsupported';
}
