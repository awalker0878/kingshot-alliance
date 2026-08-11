<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Enums;

enum KingdomIngestionCandidateState: string
{
    case Pending = 'pending';
    case Quarantined = 'quarantined';
    case Rejected = 'rejected';
    case Promoted = 'promoted';
}
