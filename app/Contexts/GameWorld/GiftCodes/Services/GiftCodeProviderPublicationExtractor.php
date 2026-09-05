<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;

final class GiftCodeProviderPublicationExtractor
{
    public function __construct(private GiftCodeEvidenceExtractor $extractor) {}

    /** @return list<GiftCodeIngestionObservation> */
    public function observations(
        GiftCodeProviderPublication $publication,
        string $parserVersion,
        bool $verificationPassed,
    ): array {
        $contentFingerprint = hash('sha256', $publication->content);
        $observations = [];
        foreach ($this->extractor->extract($publication->content, $publication->publishedAt) as $evidence) {
            $observations[] = new GiftCodeIngestionObservation(
                code: $evidence['code'],
                assertion: 'available',
                assertionPayload: null,
                sourceUrl: $publication->sourceUrl,
                claimedExpiresAt: $evidence['claimed_expires_at'],
                expiryPrecision: $evidence['expiry_precision'],
                expiryTimezone: $evidence['expiry_timezone'],
                publishedAt: $publication->publishedAt,
                sourceVersion: $publication->provider.':'.$publication->providerItemId,
                retrievalVersion: $publication->retrievalVersion,
                parserVersion: $parserVersion,
                contentFingerprint: $contentFingerprint,
                rawEvidenceRef: $publication->sourceUrl.'#gift-code='.rawurlencode($evidence['code']),
                verificationPassed: $verificationPassed,
            );
            if ($evidence['applicability'] !== null) {
                $observations[] = new GiftCodeIngestionObservation(
                    code: $evidence['code'],
                    assertion: 'applicability',
                    assertionPayload: $evidence['applicability'],
                    sourceUrl: $publication->sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $publication->publishedAt,
                    sourceVersion: $publication->provider.':'.$publication->providerItemId,
                    retrievalVersion: $publication->retrievalVersion,
                    parserVersion: $parserVersion,
                    contentFingerprint: $contentFingerprint,
                    rawEvidenceRef: $publication->sourceUrl.'#gift-code-applicability='.rawurlencode($evidence['code']),
                    verificationPassed: $verificationPassed,
                );
            }
            if ($evidence['reward'] !== null) {
                $observations[] = new GiftCodeIngestionObservation(
                    code: $evidence['code'],
                    assertion: 'reward',
                    assertionPayload: $evidence['reward'],
                    sourceUrl: $publication->sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $publication->publishedAt,
                    sourceVersion: $publication->provider.':'.$publication->providerItemId,
                    retrievalVersion: $publication->retrievalVersion,
                    parserVersion: $parserVersion,
                    contentFingerprint: $contentFingerprint,
                    rawEvidenceRef: $publication->sourceUrl.'#gift-code-reward='.rawurlencode($evidence['code']),
                    verificationPassed: $verificationPassed,
                );
            }
        }

        return $observations;
    }
}
