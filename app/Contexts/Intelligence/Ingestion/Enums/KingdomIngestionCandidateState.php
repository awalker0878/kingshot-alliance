<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Ingestion\Enums;

enum KingdomIngestionCandidateState: string
{
    case Pending = 'pending';
    case Quarantined = 'quarantined';
    case Rejected = 'rejected';
    case Promoted = 'promoted';
}
