<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Architecture;

use PHPUnit\Framework\TestCase;
use Tests\Support\RepositoryPath;

final class TransferEvidenceWriteBoundaryTest extends TestCase
{
    public function test_every_transfer_write_that_accepts_evidence_uses_the_owner_reference_guard(): void
    {
        $paths = [
            'app/Contexts/GameWorld/KingdomTransfers/Actions/SaveTransferWindow.php',
            'app/Contexts/GameWorld/KingdomTransfers/Services/TransferGroupWriter.php',
            'app/Contexts/GameWorld/KingdomTransfers/Services/TransferKingdomConditionWriter.php',
            'app/Contexts/GameWorld/KingdomTransfers/Services/TransferObservationWriter.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents(RepositoryPath::fromRoot($path));
            self::assertIsString($source, $path);
            self::assertStringContainsString('TransferEvidenceReferenceGuard', $source, $path);
            self::assertStringContainsString('assertUsable', $source, $path);
        }
    }

    public function test_provenance_identity_participates_in_transfer_idempotency_fingerprints(): void
    {
        $observation = file_get_contents(RepositoryPath::fromRoot(
            'app/Contexts/GameWorld/KingdomTransfers/Services/TransferObservationWriter.php',
        ));
        self::assertIsString($observation);
        self::assertStringContainsString('$evidenceId ?? \'\'', $observation);
        self::assertStringContainsString('$details ?? \'\'', $observation);

        $condition = file_get_contents(RepositoryPath::fromRoot(
            'app/Contexts/GameWorld/KingdomTransfers/Services/TransferKingdomConditionWriter.php',
        ));
        self::assertIsString($condition);
        self::assertStringContainsString('$evidenceId ?? \'\'', $condition);
    }
}
