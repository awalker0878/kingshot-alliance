<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Observations\Enums;

enum SpatialObservationCoverageKind: string
{
    case CompleteHive = 'complete_hive';
    case CompleteVisibleRegion = 'complete_visible_region';
    case PartialRegion = 'partial_region';
    case SingleObject = 'single_object';
    case UnknownCoverage = 'unknown_coverage';
}
