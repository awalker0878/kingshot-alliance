<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use Illuminate\Validation\ValidationException;

final readonly class TransferEvidenceReferenceGuard
{
    public function __construct(private EvidenceReferenceLookup $evidence) {}

    public function assertUsable(
        string $allianceId,
        TransferSourceType $sourceType,
        ?string $evidenceId,
    ): ?string {
        $reference = $evidenceId === null ? null : trim($evidenceId);
        if ($reference === '') {
            $reference = null;
        }

        if ($reference === null) {
            if ($sourceType === TransferSourceType::Evidence) {
                throw ValidationException::withMessages([
                    'evidence_id' => 'An approved Evidence reference is required for an Evidence source.',
                ]);
            }

            return null;
        }

        if (! $this->evidence->belongsToAlliance($reference, $allianceId)) {
            throw ValidationException::withMessages([
                'evidence_id' => 'The Evidence reference is not available in this Alliance.',
            ]);
        }

        if (
            $sourceType === TransferSourceType::Evidence
            && ! $this->evidence->isApprovedForAlliance($reference, $allianceId)
        ) {
            throw ValidationException::withMessages([
                'evidence_id' => 'An Evidence source must reference the latest approved Evidence review.',
            ]);
        }

        return $reference;
    }
}
