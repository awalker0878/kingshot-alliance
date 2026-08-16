<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Enums;

enum ContributionDataClass: string
{
    case RecordedFact = 'recorded_fact';
    case CalculatedMetric = 'calculated_metric';
    case SubjectiveAssessment = 'subjective_assessment';
}
