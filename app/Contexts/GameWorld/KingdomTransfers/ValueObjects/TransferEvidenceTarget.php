<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

final readonly class TransferEvidenceTarget
{
    public function __construct(
        public string $allianceId,
        public string $transferPlanId,
        public string $transferParticipantId,
        public string $transferWindowId,
        public string $direction,
        public ?string $targetKingdomId,
    ) {}
}
