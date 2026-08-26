<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\ValueObjects;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;

final readonly class ReviewedTransferEvidenceCommitCommand
{
    /** @param list<int> $officialGroupKingdomNumbers */
    public function __construct(
        public string $commitAttemptId,
        public string $evidenceId,
        public string $reviewId,
        public string $allianceId,
        public string $transferPlanId,
        public string $transferParticipantId,
        public string $transferWindowId,
        public ?string $targetKingdomId,
        public EvidenceKind $kind,
        public string $schemaVersion,
        public string $idempotencyKey,
        public string $observedAt,
        public ?string $validUntil,
        public ?int $governorPower,
        public ?int $transferScore,
        public ?int $transferPassesAvailable,
        public ?int $transferPassesRequired,
        public ?string $invitationStatus,
        public ?int $targetPowerCap,
        public ?string $kingdomClassification,
        public ?string $officialGroupIdentifier,
        public array $officialGroupKingdomNumbers,
    ) {}
}
