<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\ValueObjects;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

final readonly class TransferEvidencePreviewInput
{
    /** @param list<int> $officialGroupKingdomNumbers */
    public function __construct(
        public EvidenceKind $kind,
        public string $observedAt,
        public ?string $validUntil = null,
        public ?int $governorPower = null,
        public ?int $transferScore = null,
        public ?int $passesAvailable = null,
        public ?int $passesRequired = null,
        public ?string $invitationStatus = null,
        public ?int $targetPowerCap = null,
        public ?string $kingdomClassification = null,
        public ?string $officialGroupIdentifier = null,
        public array $officialGroupKingdomNumbers = [],
    ) {}
}
