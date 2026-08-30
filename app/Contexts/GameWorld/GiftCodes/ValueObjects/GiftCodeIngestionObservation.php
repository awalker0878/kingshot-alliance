<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\ValueObjects;

final readonly class GiftCodeIngestionObservation
{
    /** @param array<string,mixed>|null $assertionPayload */
    public function __construct(
        public string $code,
        public string $assertion,
        public ?array $assertionPayload,
        public ?string $sourceUrl,
        public ?string $claimedExpiresAt,
        public ?string $expiryPrecision,
        public ?string $expiryTimezone,
        public ?string $publishedAt,
        public string $sourceVersion,
        public string $retrievalVersion,
        public string $parserVersion,
        public string $contentFingerprint,
        public string $rawEvidenceRef,
        public bool $verificationPassed,
    ) {}
}
