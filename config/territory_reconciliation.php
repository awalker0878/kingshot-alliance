<?php

declare(strict_types=1);

// Comparison tolerances/freshness live here; renderable map bounds and coordinate transforms come only from the pinned KingdomMaps dataset.
return [
    'position_tolerance_tiles' => (float) env('TERRITORY_RECONCILIATION_POSITION_TOLERANCE', 1.0),
    'banner_match_max_tiles' => (float) env('TERRITORY_RECONCILIATION_BANNER_MATCH_MAX', 25.0),
    'fresh_seconds' => (int) env('TERRITORY_RECONCILIATION_FRESH_SECONDS', 3600),
    'aging_seconds' => (int) env('TERRITORY_RECONCILIATION_AGING_SECONDS', 21600),
];
