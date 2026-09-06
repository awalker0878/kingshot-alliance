<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeProviderPublication;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeProviderPublicationExtractor;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceDeliveryOutcome;

final readonly class IngestGiftCodeProviderPublication
{
    public function __construct(
        private GiftCodeProviderPublicationExtractor $extractor,
        private IngestApprovedGiftCodeObservation $ingest,
    ) {}

    public function handle(
        string $sourceId,
        GiftCodeProviderPublication $publication,
        string $parserVersion,
        bool $verificationPassed = true,
    ): GiftCodeSourceDeliveryOutcome {
        $accepted = 0;
        $quarantined = 0;
        $observations = $this->extractor->observations($publication, $parserVersion, $verificationPassed);

        foreach ($observations as $observation) {
            $result = $this->ingest->handle($sourceId, $observation);
            $accepted += $result['accepted'] ? 1 : 0;
            $quarantined += $result['quarantined'] ? 1 : 0;
        }

        return new GiftCodeSourceDeliveryOutcome(
            status: $quarantined > 0 ? 'quarantined' : 'processed',
            observations: count($observations),
            accepted: $accepted,
            quarantined: $quarantined,
        );
    }
}
